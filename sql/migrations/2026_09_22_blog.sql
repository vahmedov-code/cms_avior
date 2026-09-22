-- Additive migration; never reads or changes customers, orders or credentials.
CREATE TABLE IF NOT EXISTS blog_posts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(120) NOT NULL UNIQUE,
 title VARCHAR(200) NOT NULL,
 category VARCHAR(40) NOT NULL,
 tags VARCHAR(500) NOT NULL DEFAULT '',
 excerpt TEXT NOT NULL,
 body MEDIUMTEXT NOT NULL,
 cover VARCHAR(200) NOT NULL,
 cover_alt VARCHAR(250) NOT NULL,
 cover_caption VARCHAR(500) NOT NULL DEFAULT '',
 author VARCHAR(160) NOT NULL,
 seo_title VARCHAR(200) NOT NULL,
 seo_description VARCHAR(350) NOT NULL,
 service VARCHAR(100) NOT NULL,
 editor_notes TEXT NOT NULL,
 published_json MEDIUMTEXT NULL,
 scheduled_json MEDIUMTEXT NULL,
 scheduled_at DATETIME NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX blog_schedule (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS blog_media (
 id CHAR(32) PRIMARY KEY,
 original_name VARCHAR(255) NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
