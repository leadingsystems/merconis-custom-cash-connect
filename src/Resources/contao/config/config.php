<?php

namespace Merconis\Custom;

if (TL_MODE == 'BE') {
	$GLOBALS['TL_CSS'][] = 'bundles/leadingsystemsmerconiscustom/be/css/style.css';
}

if (TL_MODE === 'FE') {
    $GLOBALS['LS_API_HOOKS']['apiReceiver_processRequest'][] = array('Merconis\Custom\apiController_import', 'processRequest');
    $GLOBALS['LS_API_HOOKS']['apiReceiver_processRequest'][] = array('Merconis\Custom\apiController_cleanup', 'processRequest');
}

$GLOBALS['MERCONIS_HOOKS']['import_afterUpdatingProductData'][] = ['Merconis\Custom\merconis_custom_helper', 'modifyImportedProductRecord'];
$GLOBALS['MERCONIS_HOOKS']['import_afterInsertingProductData'][] = ['Merconis\Custom\merconis_custom_helper', 'modifyImportedProductRecord'];

$GLOBALS['MERCONIS_HOOKS']['crossSellerHookSelection'][] = array('Merconis\Custom\merconis_custom_helper', 'merconis_hook_crossSellerHookSelection');

$GLOBALS['MERCONIS_HOOKS']['afterCheckout'][] = array('Merconis\Custom\merconis_custom_helper', 'merconis_hook_afterCheckout');
$GLOBALS['MERCONIS_HOOKS']['storeCartItemInOrder'][] = array('Merconis\Custom\merconis_custom_helper', 'merconis_hook_storeCartItemInOrder');

$GLOBALS['MERCONIS_HOOKS']['checkIfPaymentOrShippingMethodIsAllowed'][] = array('Merconis\Custom\merconis_custom_helper', 'merconis_hook_checkIfPaymentOrShippingMethodIsAllowed');