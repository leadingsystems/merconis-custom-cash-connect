<?php

namespace Merconis\Custom;

use function LeadingSystems\Helpers\ls_getFilePathFromVariableSources;
use Merconis\Core\ls_shop_singularStorage;

class merconis_productCleanupAutomator
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

    private $str_pathToInputFolder = TL_ROOT . '/files/cashImportExport/cleanup';
    private $str_pathToArchiveFolder = TL_ROOT . '/files/cashImportExport/cleanupAutomator/archive';
    private $str_pathToProcessingFolder = TL_ROOT . '/files/cashImportExport/cleanupAutomator/processing';

    private $str_fileNamePattern = '*.[tT][xX][tT]';

    private $str_pathToFileCurrentlyBeingProcessed = '';

    public function run($obj_apiReceiver)
    {
        $this->makeSureNecessaryFoldersExist();

        $this->obj_apiReceiver = $obj_apiReceiver;

        $this->getFileCurrentlyInProcessingFolder();

        /*
         * If there is no file in the processing folder, the status is set to 'noFileInProcessingFolder'.
         * If there is an file in the processing folder, the status is set to 'fileInProcessingFolder' unless there's already another status set.
         */
        if (!$this->str_pathToFileCurrentlyBeingProcessed) {
            ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = 'noFileInProcessingFolder';
        } else {
            if (
                !ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus
                || ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus === 'cleanupFinished'
            ) {
                ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = 'fileInProcessingFolder';
            }
        }

        /*
         * If there's no file in the processing folder, we try to get a file from the input folder
         */
        if (ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus === 'noFileInProcessingFolder') {
            $this->getFileToProcess();

            /*
             * If we couldn't get a file because none exists, we reset the status so that the next call
             * begins from the start and then we end the cleanup attempt by ending the automator run
             */
            if (ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus === 'noFileInProcessingFolder') {
                ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = null;
                $this->obj_apiReceiver->success();
                $this->obj_apiReceiver->set_data('no cleanup file(s) available');
                \System::log('Checking for cleanup file(s): None available', 'MERCONIS PRODUCT CLEANUP AUTOMATOR', TL_MERCONIS_IMPORTER);
                return;
            }
        }


        /*
         * If a file is in the processing folder but it hasn't been validated yet, we validate it
         */
        if (ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus === 'fileInProcessingFolder') {
            if ($this->validateFile()) {
                ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = 'currentFileValid';
                /*
                 * After we successfully validated the current file we end the current automator run
                 */
                $this->obj_apiReceiver->success();
                $this->obj_apiReceiver->set_data([
                    'automatorStatus' => ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus,
                    'fileInfo' => basename($this->str_pathToFileCurrentlyBeingProcessed)
                ]);
                \System::log('Validating cleanup file ('.basename($this->str_pathToFileCurrentlyBeingProcessed).'): VALID', 'MERCONIS PRODUCT CLEANUP AUTOMATOR', TL_MERCONIS_IMPORTER);
                return;
            } else {
                /*
                 * If the current file is invalid, we move it back to the input folder and reset the status.
                 * The next calls will then do exactly the same over and over until someone fixes or removes the invalid
                 * file in/from the input folder
                 */
                $this->moveInvalidFileBackToInputFolder();
                ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = 'cleanupFinished';
                $this->obj_apiReceiver->error();
                $this->obj_apiReceiver->set_message('cleanup file ('.basename($this->str_pathToFileCurrentlyBeingProcessed).') is not valid');
                \System::log('Validating cleanup file ('.basename($this->str_pathToFileCurrentlyBeingProcessed).'): INVALID', 'MERCONIS PRODUCT CLEANUP AUTOMATOR', TL_MERCONIS_IMPORTER);
                return;
            }
        }

        if (
            ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus === 'currentFileValid'
            || ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus === 'cleanupInProgress'
        ) {
            $this->performCleanup();
            $this->obj_apiReceiver->success();
            $this->moveFinishedFileToArchiveFolder();
            ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = 'cleanupFinished';
            $this->obj_apiReceiver->set_data([
                'automatorStatus' => ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus,
                'fileInfo' => basename($this->str_pathToFileCurrentlyBeingProcessed)
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
        $arr_filesInInputFolder = glob($this->str_pathToInputFolder . "/" . $this->str_fileNamePattern);

        if (!is_array($arr_filesInInputFolder) || !count($arr_filesInInputFolder)) {
            ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = 'noFileInProcessingFolder';
            return;
        }

        array_multisort(array_map('filemtime', $arr_filesInInputFolder), SORT_ASC, $arr_filesInInputFolder);

        $str_fileToProcessNext = $arr_filesInInputFolder[0];
        $str_movedFilename = $this->str_pathToProcessingFolder . '/' . basename($str_fileToProcessNext);
        rename($str_fileToProcessNext, $str_movedFilename);
        $this->str_pathToFileCurrentlyBeingProcessed = $str_movedFilename;
        ls_shop_singularStorage::getInstance()->str_productCleanupAutomatorStatus = 'fileInProcessingFolder';
    }

    private function moveInvalidFileBackToInputFolder()
    {
        $str_movedBackFilename = $this->str_pathToInputFolder . '/' . basename($this->str_pathToFileCurrentlyBeingProcessed);
        rename($this->str_pathToFileCurrentlyBeingProcessed, $str_movedBackFilename);
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

    private function validateFile() {
        /*
         * The cleanup file is simply expected to contain a comma separated list of article numbers that still exist
         * and must not be removed. Since there's no expected syntax for a valid article number we could only check
         * if the file contains a comma separated list of values. But since the file would also be valid if it contained
         * only one article number (and therefore no comma at all), we can't even check for commas.
         *
         * This means that we don't actually validate the file. If sometime in the future we find something that
         * we actually would want to validate, we can change this function. Until the, we just return true.
         */
        return true;
    }

    private function performCleanup() {
        $str_fileContent = file_get_contents($this->str_pathToFileCurrentlyBeingProcessed);
        $str_fileContent = preg_replace('/productcode\s*/s', '', $str_fileContent);
        $str_fileContent = trim($str_fileContent);

        $arr_stillExistingProductCodes = explode(',', $str_fileContent);

        if (
                !is_array($arr_stillExistingProductCodes)
            ||  !count($arr_stillExistingProductCodes)
        ) {
            return;
        }

        /*
         * First we empty the temporary table
         */
        \Database::getInstance()
            ->prepare("
                TRUNCATE TABLE `tl_ls_shop_tmp_product_cleanup_remaining_products`
            ")
            ->execute();

        /*
         * Then we write all the still existing product codes in the table
         */
        \Database::getInstance()
            ->prepare("
                INSERT INTO `tl_ls_shop_tmp_product_cleanup_remaining_products`
                VALUES ('".implode("'),('", $arr_stillExistingProductCodes)."')
            ")
            ->execute();

        /*
         * Then we delete every product that does not have a match in the table which contains all still existing product codes
         */
        \Database::getInstance()
            ->prepare("
                DELETE FROM   `tl_ls_shop_product`
                WHERE         `lsShopProductCode`
                NOT IN        (
                    SELECT    `product_code`
                    FROM      `tl_ls_shop_tmp_product_cleanup_remaining_products`
                    WHERE     `product_code` IS NOT NULL
                )
            ")
            ->execute();
    }
}