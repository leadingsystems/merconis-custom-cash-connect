<?php

namespace Merconis\Custom;

use function LeadingSystems\Helpers\ls_getFilePathFromVariableSources;
use Merconis\Core\ls_shop_singularStorage;

class merconis_productImportAutomator
{
    protected static $objInstance;

    protected function __construct()
    {
    }

    final private function __clone()
    {
    }

    public static function getInstance()
    {
        if (!is_object(self::$objInstance)) {
            self::$objInstance = new self();
        }

        return self::$objInstance;
    }

    /** @var \LeadingSystems\Api\ls_apiController $obj_apiReceiver */
    private $obj_apiReceiver = null;

    /** @var \Merconis\Core\ls_shop_importController $obj_importController */
    private $obj_importController = null;

    private $str_pathToInputFolder = TL_ROOT . '/files/cashImportExport/import';
    private $str_pathToArchiveFolder = TL_ROOT . '/files/cashImportExport/importAutomator/archive';
    private $str_pathToProcessingFolder = null;

    private $str_fileNamePattern = '*.[cC][sS][vV]';

    private $str_pathToFileCurrentlyBeingProcessed = '';

    public function run($obj_apiReceiver)
    {
        $this->str_pathToProcessingFolder = TL_ROOT . '/' . ls_getFilePathFromVariableSources($GLOBALS['TL_CONFIG']['ls_shop_standardProductImportFolder']);

        $this->makeSureNecessaryFoldersExist();

        $this->obj_apiReceiver = $obj_apiReceiver;

        $this->getFileCurrentlyInProcessingFolder();

        /*
         * If there is no import file in the processing folder, the status is set to 'noImportFileInProcessingFolder'.
         * If there is an import file in the processing folder, the status is set to '' unless there's already another status set.
         */
        if (!$this->str_pathToFileCurrentlyBeingProcessed) {
            ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = 'noImportFileInProcessingFolder';
        } else {
            if (!ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus) {
                ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = 'importFileInProcessingFolder';
            }
        }

        /*
         * If there's no import file in the processing folder, we try to get an import file from the input folder
         */
        if (ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus === 'noImportFileInProcessingFolder') {
            $this->getFileToProcess();

            /*
             * If we couldn't get an import file because none exists, we reset the status so that the next call
             * begins from the start and then we end the import attempt by ending the automator run
             */
            if (ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus === 'noImportFileInProcessingFolder') {
                ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = null;
                $this->obj_apiReceiver->success();
                $this->obj_apiReceiver->set_data('no import file(s) available');
                \System::log('Checking for import file(s): None available', 'MERCONIS PRODUCT IMPORT AUTOMATOR', TL_MERCONIS_IMPORTER);
                return;
            }
        }

        /* ::: ATTENTION :::
         * The import controller must not be instantiated earlier because it will determine the file to import
         * in its constructor only once. Therefore, if we instantiate the controller before we could make sure that
         * there actually is an import file, all further actions will fail even if we move an import file to the
         * import folder.
         */
        $this->obj_importController = new \Merconis\Core\ls_shop_importController(false);

        /*
         * If an import file is in the processing folder but it hasn't been validated yet, we validate it
         */
        if (ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus === 'importFileInProcessingFolder') {
            if ($this->obj_importController->validateFile()) {
                ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = 'currentImportFileValid';
                /*
                 * After we successfully validated the current import file we end the current automator run
                 */
                $this->obj_apiReceiver->success();
                $this->obj_apiReceiver->set_data([
                    'automatorStatus' => ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus,
                    'fileInfo' => $this->obj_importController->getImportFileInfoMinimal()
                ]);
                \System::log('Validating import file ('.basename($this->str_pathToFileCurrentlyBeingProcessed).'): VALID', 'MERCONIS PRODUCT IMPORT AUTOMATOR', TL_MERCONIS_IMPORTER);
                return;
            } else {
                $this->moveInvalidFile();
                ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = null;
                $this->obj_apiReceiver->error();
                $this->obj_apiReceiver->set_message('import file ('.basename($this->str_pathToFileCurrentlyBeingProcessed).') is not valid');
                \System::log('Validating import file ('.basename($this->str_pathToFileCurrentlyBeingProcessed).'): INVALID', 'MERCONIS PRODUCT IMPORT AUTOMATOR', TL_MERCONIS_IMPORTER);
                merconis_custom_helper::sendImportStatusEmail('INVALID IMPORT FILE', 'import file ('.basename($this->str_pathToFileCurrentlyBeingProcessed).') is not valid and has been moved to the archive. Please take care of the problem.');
                return;
            }
        }

        if (
            ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus === 'currentImportFileValid'
            || ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus === 'importInProgress'
        ) {
            $this->obj_importController->importFile();
            $this->obj_apiReceiver->success();
            $arr_importInformation = $this->obj_importController->getImportFileInfoMinimal();

            if ($arr_importInformation['status'] === 'ok') {
                \System::log('Importing file in progress (status: '.$arr_importInformation['status'].'; currently processing data row type: '.$arr_importInformation['currentlyProcessingDataRowType'].'; '.basename($this->str_pathToFileCurrentlyBeingProcessed).')', 'MERCONIS PRODUCT IMPORT AUTOMATOR', TL_MERCONIS_IMPORTER);
                ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = 'importInProgress';
            } else if ($arr_importInformation['status'] === 'importFinished') {
                \System::log('Importing file finished ('.basename($this->str_pathToFileCurrentlyBeingProcessed).')', 'MERCONIS PRODUCT IMPORT AUTOMATOR', TL_MERCONIS_IMPORTER);
//                merconis_custom_helper::sendImportStatusEmail('Import finished', 'Importing file finished ('.basename($this->str_pathToFileCurrentlyBeingProcessed).')');

                /*
                 * If the import has been successfully finished, we move the current import file to the archive
                 * and reset the status. The next automator run can then start from the beginning.
                 */
                $this->moveFinishedFileToArchiveFolder();
                ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = null;
            } else if ($arr_importInformation['status'] === 'importFailed') {
                /*
                 * ::: ATTENTION/IMPORTANT :::
                 * Processing import files in the chronologically right order is very important. Therefore, we can't just
                 * move an import file to a "failed" folder if the import failed because then the next automator run would
                 * process the next file.
                 *
                 * Instead, we leave the failed import file exactly where it is and let the administrator
                 * handle the issue manually
                 */
                ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = 'importFailed';
                \System::log('WARNING: IMPORTING FILE FAILED ('.basename($this->str_pathToFileCurrentlyBeingProcessed).')', 'MERCONIS PRODUCT IMPORT AUTOMATOR', TL_MERCONIS_IMPORTER);
                merconis_custom_helper::sendImportStatusEmail('WARNING: IMPORTING FILE FAILED', 'WARNING: IMPORTING FILE FAILED ('.basename($this->str_pathToFileCurrentlyBeingProcessed).')');
            }

            $this->obj_apiReceiver->set_data([
                'automatorStatus' => ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus,
                'fileInfo' => $arr_importInformation
            ]);
            return;
        }
    }

    /*
     * We always want to process the oldest file in the input folder first to make sure that old product data
     * can never overwrite more recent product data.
     */
    private function getFileToProcess()
    {
        $arr_filesInImportFolder = glob($this->str_pathToInputFolder . "/" . $this->str_fileNamePattern);

        if (!is_array($arr_filesInImportFolder) || !count($arr_filesInImportFolder)) {
            ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = 'noImportFileInProcessingFolder';
            return;
        }

        array_multisort(array_map('filemtime', $arr_filesInImportFolder), SORT_ASC, $arr_filesInImportFolder);

        $str_fileToProcessNext = $arr_filesInImportFolder[0];
        $str_movedFilename = $this->str_pathToProcessingFolder . '/' . basename($str_fileToProcessNext);
        rename($str_fileToProcessNext, $str_movedFilename);
        $this->str_pathToFileCurrentlyBeingProcessed = $str_movedFilename;
        ls_shop_singularStorage::getInstance()->str_productImportAutomatorStatus = 'importFileInProcessingFolder';
    }

    private function moveInvalidFile()
    {
        /*
         * The safest thing would be to move the invalid file back to the input folder because this would make sure that
         * the following files could not be imported without fixing the issue first. If we only had delta imports where
         * only modified products are included, this would be absolutely necessary because otherwise it could happen that
         * some changes would never actually be imported if no one ever fixed the problem. However, if we have a full
         * import (i.e. an input file holding all existing products) periodically, e.g. every morning, we can just skip
         * the invalid file and wait for someone to fix the issue and with the next full import everything will be just fine.
         *
        $str_movedBackFilename = $this->str_pathToInputFolder . '/' . basename($this->str_pathToFileCurrentlyBeingProcessed);
        rename($this->str_pathToFileCurrentlyBeingProcessed, $str_movedBackFilename);
         */
        $str_movedToArchiveFilename = $this->str_pathToArchiveFolder . '/' . date('Y-m-d-H-i-s_'). 'INVALID_' . basename($this->str_pathToFileCurrentlyBeingProcessed);
        rename($this->str_pathToFileCurrentlyBeingProcessed, $str_movedToArchiveFilename);
    }

    private function moveFinishedFileToArchiveFolder()
    {
        $str_movedToArchiveFilename = $this->str_pathToArchiveFolder . '/' . date('Y-m-d-H-i-s_') . basename($this->str_pathToFileCurrentlyBeingProcessed);
        rename($this->str_pathToFileCurrentlyBeingProcessed, $str_movedToArchiveFilename);
    }

    private function getFileCurrentlyInProcessingFolder()
    {
        $arr_filesInProcessingFolder = glob($this->str_pathToProcessingFolder . "/" . $this->str_fileNamePattern);
        if (!is_array($arr_filesInProcessingFolder) || !count($arr_filesInProcessingFolder)) {
            return;
        }

        if (count($arr_filesInProcessingFolder) > 1) {
            throw new \Exception('More than one file in processing folder. This is not expected and can not be handled.');
        }

        $this->str_pathToFileCurrentlyBeingProcessed = $arr_filesInProcessingFolder[0];
    }

    private function makeSureNecessaryFoldersExist() {
        if (!is_dir($this->str_pathToInputFolder)) {
            mkdir($this->str_pathToInputFolder, 0777, true);
        }

        if (!is_dir($this->str_pathToArchiveFolder)) {
            mkdir($this->str_pathToArchiveFolder, 0777, true);
        }

        if (!is_dir($this->str_pathToProcessingFolder)) {
            mkdir($this->str_pathToProcessingFolder, 0777, true);
        }
    }
}