CREATE TABLE `tl_ls_shop_tmp_product_cleanup_remaining_products` (
  `product_code` varchar(255) NOT NULL default ''
  PRIMARY KEY  (`product_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `tl_page` (
	`ls_shop_hobbyEberhardt_warengruppe` varchar(255) NOT NULL default '',
	`ls_shop_hobbyEberhardt_lieferant` varchar(255) NOT NULL default ''
	`ls_shop_hobbyEberhardt_produkteOhneBestandAusblenden` char(1) NOT NULL default ''
	KEY `ls_shop_hobbyEberhardt_warengruppe` (`ls_shop_hobbyEberhardt_warengruppe`),
	KEY `ls_shop_hobbyEberhardt_lieferant` (`ls_shop_hobbyEberhardt_lieferant`)
	KEY `ls_shop_hobbyEberhardt_produkteOhneBestandAusblenden` (`ls_shop_hobbyEberhardt_produkteOhneBestandAusblenden`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;