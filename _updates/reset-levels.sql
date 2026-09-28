-- GDIPS level reset
-- Preserves accounts/users/roles and resets only level-related content.
-- Run this in Adminer against the GDIPS database.

START TRANSACTION;

DELETE FROM comments;
DELETE FROM levelscores;
DELETE FROM actions_downloads;
DELETE FROM cpshares;
DELETE FROM demonlist;
DELETE FROM dlsubmits;
DELETE FROM suggest;
DELETE FROM dailyfeatures;
DELETE FROM events;
DELETE FROM gauntlets;
DELETE FROM mappacks;
DELETE FROM lists;
DELETE FROM levels;

ALTER TABLE comments AUTO_INCREMENT = 1;
ALTER TABLE levelscores AUTO_INCREMENT = 1;
ALTER TABLE actions_downloads AUTO_INCREMENT = 1;
ALTER TABLE cpshares AUTO_INCREMENT = 1;
ALTER TABLE demonlist AUTO_INCREMENT = 1;
ALTER TABLE dlsubmits AUTO_INCREMENT = 1;
ALTER TABLE suggest AUTO_INCREMENT = 1;
ALTER TABLE dailyfeatures AUTO_INCREMENT = 1;
ALTER TABLE events AUTO_INCREMENT = 1;
ALTER TABLE gauntlets AUTO_INCREMENT = 1;
ALTER TABLE mappacks AUTO_INCREMENT = 1;
ALTER TABLE lists AUTO_INCREMENT = 1;
ALTER TABLE levels AUTO_INCREMENT = 1;

COMMIT;
