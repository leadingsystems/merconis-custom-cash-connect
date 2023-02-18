<?php

namespace Merconis\Custom;

$GLOBALS['TL_DCA']['tl_ls_shop_product']['palettes']['default'] .= ';{lsShopHobbyEberhardt_legend},hobbyEberhardtWarengruppe,hobbyEberhardtLieferant';
$GLOBALS['TL_DCA']['tl_ls_shop_product']['fields']['hobbyEberhardtWarengruppe'] = [
    'label'			=>	&$GLOBALS['TL_LANG']['tl_ls_shop_product']['hobbyEberhardtWarengruppe'],
    'exclude' => true,
    'inputType'		=>	'text',
    'eval'			=> array('tl_class' => 'w50', 'mandatory' => true, 'decodeEntities' => true, 'maxlength'=>255),
    'sorting' => true,
    'flag' => 11,
    'search' => true,
    'sql' => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_ls_shop_product']['fields']['hobbyEberhardtLieferant'] = [
    'label'			=>	&$GLOBALS['TL_LANG']['tl_ls_shop_product']['hobbyEberhardtLieferant'],
    'exclude' => true,
    'inputType'		=>	'text',
    'eval'			=> array('tl_class' => 'w50', 'mandatory' => true, 'decodeEntities' => true, 'maxlength'=>255),
    'sorting' => true,
    'flag' => 11,
    'search' => true,
    'sql' => "varchar(255) NOT NULL default ''"
];

