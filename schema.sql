CREATE TABLE IF NOT EXISTS question (
  id INT AUTO_INCREMENT PRIMARY KEY,
  text LONGTEXT NULL,
  image VARCHAR(256) NULL,
  parent_number INT NULL,
  question_number INT NULL,
  answer VARCHAR(1) NULL,
  UNIQUE KEY question_id_uindex (id)
);


