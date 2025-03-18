-- Add and change Dreamcast UID pattern based off discoveries: https://github.com/TheGamesDB2/Website/issues/36

INSERT INTO `games_uids_patterns` (
    `platform_id`,
    `name`,
    `regex_pattern`
) VALUES (
    16,
    'serial_id',
    '\\d{5}([A-Z]|)'
);

UPDATE `games_uids_patterns` SET
    `regex_pattern` = 'T(-| |)(\\d{4,5})(-| |)(D|M|ND|N)(-| {1,2}|)(\\d{2}([A-Z]|)|)'
WHERE `regex_pattern` = 'T(-| |)(\\d{4,5})(D|M|ND|N)(-| |)(\\d{2}([A-Z]|)|)';

UPDATE `games_uids_patterns` SET
    `regex_pattern` = 'MK(-| |)\\d{4,5}((-| |)(\\d{2}|)([A-Z]|)|)'
WHERE `regex_pattern` = 'MK(-| |)\\d{5}((-| |)\\d{2}([A-Z]|)|)';
