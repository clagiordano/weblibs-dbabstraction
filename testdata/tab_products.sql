CREATE TABLE `tab_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(250) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='latin1_swedish_ci'

INSERT INTO `tab_products` set brand='brand', code='code', model='model', description='description';
