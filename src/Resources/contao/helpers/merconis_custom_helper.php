<?php
namespace Merconis\Custom;

use Contao\Email;

class merconis_custom_helper
{
    public static function sendImportStatusEmail($str_subject, $str_message) {
        $obj_email = new Email();
        $obj_email->subject = $str_subject;
        $obj_email->text = $str_message;
        $obj_email->sendTo(\Config::get('adminEmail'));
    }

    public static function modifyImportedProductRecord($int_productId) {
        $obj_dbres_productData = \Database::getInstance()
            ->prepare("
                SELECT      *
                FROM        `tl_ls_shop_product`
                WHERE       `id` = ?
            ")
            ->execute(
                $int_productId
            );

        $obj_dbres_productData->first();

        $arr_flexContentsLanguageIndependent = json_decode($obj_dbres_productData->flex_contentsLanguageIndependent);
        $arr_flexContentsFlat = [];

        foreach ($arr_flexContentsLanguageIndependent as $arr_flexContent) {
            $arr_flexContentsFlat[$arr_flexContent[0]] = $arr_flexContent[1];
        }

        /* -->
         * This example is just for inspiration:
         */

        $str_alias = \StringUtil::generateAlias($obj_dbres_productData->lsShopProductProducer).'-'.\StringUtil::generateAlias($arr_flexContentsFlat['flexContent1']).'-'.$obj_dbres_productData->alias;
        if (strlen($str_alias) > 128) {
            $str_aliasSuffix = '-'.$obj_dbres_productData->id;
            $str_alias = substr($str_alias, 0, 128 - strlen($str_aliasSuffix)).$str_aliasSuffix;
        }

        $obj_dbquery_updateProduct = \Database::getInstance()
            ->prepare("
                UPDATE    `tl_ls_shop_product`
                SET       `alias` = ?,
                          `alias_de` = ?
                WHERE     `id` = ?
            ")
            ->execute(
                $str_alias,
                $str_alias,
                $int_productId
            );
        /*
         * <--
         */
    }

    public static function merconis_hook_afterCheckout($int_orderId, $arr_order) {

//        \LeadingSystems\Helpers\lsErrorLog('$arr_order', $arr_order, 'perm');

        $float_taxInValueOfGoods = 0;
        foreach ($arr_order['totalValueOfGoodsTaxedWith'] as $arr_taxInfo) {
            $float_taxInValueOfGoods += $arr_taxInfo['amountTaxedHerewith'] / (100 + $arr_taxInfo['taxRate']) * $arr_taxInfo['taxRate'];
        }

        $arr_cashOrderOutput = [
            'technicalData' => [
                'Dateityp' => 'EBE',
                'Version' => 310,
                'Shopprogramm' => 'Merconis',
                'Shopversion' => 4,
                'Shopdomain' => 'hobby-eberhardt.de'
            ],

            'orderHead' => [
                'Satzkennung' => 700,
                'EshopBestellID' => $int_orderId,
                'EShopbestellnr' => substr($arr_order['orderNr'], 0, 30),
                'KDID' => -1,
                'EShopKDID' => $arr_order['customerNr'],
                'EShopKundennr' => substr($arr_order['customerNr'], 0, 50),
                'Firma' => substr($arr_order['customerInfo']['personalData']['company'], 0, 50),
                'Anrede' => '',
                'Titel' => '',
                'Vorname' => substr($arr_order['customerInfo']['personalData']['firstname'], 0, 50),
                'Zuname' => substr($arr_order['customerInfo']['personalData']['lastname'], 0, 50),
                'Strasse' => substr($arr_order['customerInfo']['personalData']['street'], 0, 50),
                'Hausnummer' => '',
                'Land' => strtoupper($arr_order['customerInfo']['personalData_originalOptionValues']['country']),
                'PLZ' => substr($arr_order['customerInfo']['personalData']['postal'], 0, 10),
                'Ort' => substr($arr_order['customerInfo']['personalData']['city'], 0, 50),
                'telefon1' => substr($arr_order['customerInfo']['personalData']['phone'], 0, 50),
                'telefax' => '',
                'email' => substr($arr_order['customerInfo']['personalData']['email'], 0, 50),

                'Lieferid' => -1,
                'eshoplieferid' => -1,
                'lieferfirma' => substr($arr_order['customerInfo']['personalData_originalOptionValues']['useDeviantShippingAddress'] ? $arr_order['customerInfo']['personalData']['company_alternative'] : '', 0, 50),
                'lieferanrede' => '',
                'Lieftitel' => '',
                'liefervorname' => substr($arr_order['customerInfo']['personalData_originalOptionValues']['useDeviantShippingAddress'] ? $arr_order['customerInfo']['personalData']['firstname_alternative'] : '', 0, 50),
                'lieferzuname' => substr($arr_order['customerInfo']['personalData_originalOptionValues']['useDeviantShippingAddress'] ? $arr_order['customerInfo']['personalData']['lastname_alternative'] : '', 0, 50),
                'lieferstrasse' => substr($arr_order['customerInfo']['personalData_originalOptionValues']['useDeviantShippingAddress'] ? $arr_order['customerInfo']['personalData']['street_alternative'] : '', 0, 50),
                'LiefHausnummer' => '',
                'lieferland' => $arr_order['customerInfo']['personalData_originalOptionValues']['useDeviantShippingAddress'] ? $arr_order['customerInfo']['personalData_originalOptionValues']['country_alternative'] : '',
                'lieferplz' => substr($arr_order['customerInfo']['personalData_originalOptionValues']['useDeviantShippingAddress'] ? $arr_order['customerInfo']['personalData']['postal_alternative'] : '', 0, 10),
                'lieferort' => substr($arr_order['customerInfo']['personalData_originalOptionValues']['useDeviantShippingAddress'] ? $arr_order['customerInfo']['personalData']['city_alternative'] : '', 0, 50),
                'liefertelefon1' => '',
                'Liefertelefax' => '',

                'Bestellwert' => number_format($arr_order['totalValueOfGoods'], 2, ',', ''), // Warenwert
                'Fracht' => number_format($arr_order['shippingMethod']['amount'], 2, ',', ''), // Versandkosten
                'Nachnahme' => number_format($arr_order['paymentMethod']['amount'], 2, ',', ''), // Zahlungsgebühr
                'Rabatt' => number_format($arr_order['couponsTotalValue'], 2, ',', ''), // Gutschein
                'Gesamtwert' => number_format($arr_order['invoicedAmount'], 2, ',', ''), // Rechnungsbetrag = Warenwert + Versandkosten + Zahlungsgebühr + Gutschein
                'Zahlungsartid' => $arr_order['paymentMethod']['id'],
                'auftragdatum' => date('d.m.Y H:i:s', $arr_order['orderDateUnixTimestamp']),
                'Kontonr' => '',
                'Blz' => '',
                'Kontoinhaber' => '',
                'Bank' => '',
                'Iban' => '',
                'Swift' => '',
                'Versandart' => $arr_order['shippingMethod']['id'],
                'Umsatzsteuerid' => substr(str_replace(' ', '', $arr_order['customerInfo']['personalData']['VATID']), 0, 20),
                'Teillieferung' => 0,
                'Bestellwertnetto' => number_format($arr_order['totalValueOfGoods'] - $float_taxInValueOfGoods, 2, ',', ''), // Warenwert netto
                'Gesamtwertnetto' => number_format($arr_order['invoicedAmountNet'], 2, ',', ''), // Rechnungsbetrag netto
                'LieferungaufLieferschein' => 0,
                'Strecke' => 0,
                'Geburtstag' => '',
                'Newsletter' => 0,
                'AnPackstation' => 0,
                'PackstationNr' => '',
                'PackstationPostNr.' => '',
                'PackstationPlz' => '',
                'PackstationOrt' => '',
                'Zaehler' => -1
            ],

            'orderItems' => [

            ]
        ];

        foreach ($arr_order['items'] as $int_posNr => $arr_orderItem) {
            $arr_cashOrderOutput['orderItems'][] = [
                'Satzkennung' => 710,
                'EshopBestellID' => $int_orderId,
                'Positionid' => $int_posNr + 1,
                'ArtikelId' => $arr_orderItem['artNr'],
                'Artikelnr' => $arr_orderItem['extendedInfo']['cashArtikelNr'],
                'Text' => substr($arr_orderItem['productTitle'], 0, 100),
                'Menge' => number_format($arr_orderItem['quantity'], 3, ',', ''),
                'VKPreis' => number_format($arr_orderItem['price'], 2, ',', ''),
                'Vkpreisnetto' => number_format($arr_orderItem['price'] / (100 + $arr_orderItem['taxPercentage']) * 100, 2, ',', ''),
                'VkpreisGesamt' => number_format($arr_orderItem['priceCumulative'], 2, ',', ''),
                'VkpreisnettoGesamt' => number_format($arr_orderItem['priceCumulative'] / (100 + $arr_orderItem['taxPercentage']) * 100, 2, ',', ''),
                'Mwstid' => -1,
                'MwstPz' => number_format($arr_orderItem['taxPercentage'], 2, ',', ''),
                'Preisvorschlag' => 0,
                'Varianten' => '',
                'AltePositionid' => ''
            ];
        }

        file_put_contents(TL_ROOT.'/files/cashImportExport/orderExport/humanReadable/EshopBestellung_'.$int_orderId.'.txt', print_r($arr_cashOrderOutput, true));

        $str_contentForCtfFile = '';
        $str_contentForCtfFile .= implode('~', $arr_cashOrderOutput['technicalData']);
        $str_contentForCtfFile .= "\r\n".implode('~', $arr_cashOrderOutput['orderHead']);
        foreach ($arr_cashOrderOutput['orderItems'] as $arr_orderItem) {
            $str_contentForCtfFile .= "\r\n".implode('~', $arr_orderItem);
        }

        file_put_contents(TL_ROOT.'/files/cashImportExport/orderExport/ctf/EshopBestellung_'.$int_orderId.'.ctf', iconv('UTF-8', 'Windows-1252//TRANSLIT', $str_contentForCtfFile));
    }

    public static function merconis_hook_storeCartItemInOrder($arr_item, $obj_product) {
        $arr_item['extendedInfo']['cashArtikelNr'] = $obj_product->_flexContentExistsLanguageIndependent('flexContent8') ? $obj_product->_flexContentsLanguageIndependent['flexContent8'] : '999999999999988';

        $bln_imageExists = false;
        $str_placeholderImageFullPath = \LeadingSystems\Helpers\ls_getFilePathFromVariableSources($GLOBALS['TL_CONFIG']['ls_shop_standardProductImageFolder']) . '/placeholder-shop.png';

        if ($obj_product->_flexContentExistsLanguageIndependent('flexContent2')) {
            $arr_imagePaths = explode(',', $obj_product->_flexContentsLanguageIndependent['flexContent2']);
            $str_imageToShow = str_replace('\\', '/', str_replace('bilder\\', '', $arr_imagePaths[0]));
            $str_imageToShowFullPath = \LeadingSystems\Helpers\ls_getFilePathFromVariableSources($GLOBALS['TL_CONFIG']['ls_shop_standardProductImageFolder']) . '/' . $str_imageToShow;
            $bln_imageExists = file_exists($str_imageToShowFullPath);
        }

        $arr_item['extendedInfo']['_mainImage'] = $bln_imageExists ? $str_imageToShowFullPath : ($str_placeholderImageFullPath ? $str_placeholderImageFullPath : '');

        return $arr_item;
    }
}
