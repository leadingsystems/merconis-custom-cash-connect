<?php

namespace Merconis\Core;

$GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = preg_replace('/(;\{lsShop_legend\})/siU','\\1,ls_shop_hobbyEberhardt_warengruppe,ls_shop_hobbyEberhardt_lieferant,ls_shop_hobbyEberhardt_produkteOhneBestandAusblenden',$GLOBALS['TL_DCA']['tl_page']['palettes']['regular']);

$GLOBALS['TL_DCA']['tl_page']['fields']['ls_shop_hobbyEberhardt_warengruppe'] = array(
	'exclude' => true,
	'label' => &$GLOBALS['TL_LANG']['tl_page']['ls_shop_hobbyEberhardt_warengruppe'],
	'inputType' => 'text',
	'eval' => array('tl_class'=>'w50', 'decodeEntities' => true)
);
		
$GLOBALS['TL_DCA']['tl_page']['fields']['ls_shop_hobbyEberhardt_lieferant'] = array(
	'exclude' => true,
	'label' => &$GLOBALS['TL_LANG']['tl_page']['ls_shop_hobbyEberhardt_lieferant'],
	'inputType' => 'text',
	'eval' => array('tl_class'=>'w50', 'decodeEntities' => true)
);

$GLOBALS['TL_DCA']['tl_page']['fields']['ls_shop_hobbyEberhardt_produkteOhneBestandAusblenden'] = array(
	'exclude' => true,
	'label' => &$GLOBALS['TL_LANG']['tl_page']['ls_shop_hobbyEberhardt_produkteOhneBestandAusblenden'],
	'inputType' => 'checkbox',
	'eval' => array('tl_class'=>'w50', 'decodeEntities' => true)
);