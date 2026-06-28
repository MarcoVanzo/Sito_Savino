-- Crea il database di test per PHPUnit
-- Eseguito automaticamente da MySQL al primo avvio del container
CREATE DATABASE IF NOT EXISTS `sito_savino_test`;
GRANT ALL PRIVILEGES ON `sito_savino_test`.* TO 'sito_savino'@'%';
FLUSH PRIVILEGES;
