<?php
/**
 * Основные параметры WordPress.
 *
 * Скрипт для создания wp-config.php использует этот файл в процессе
 * установки. Необязательно использовать веб-интерфейс, можно
 * скопировать файл в "wp-config.php" и заполнить значения вручную.
 *
 * Этот файл содержит следующие параметры:
 *
 * * Настройки MySQL
 * * Секретные ключи
 * * Префикс таблиц базы данных
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** Параметры MySQL: Эту информацию можно получить у вашего хостинг-провайдера ** //
/** Имя базы данных для WordPress */
define('DB_NAME', 'wordpressbd');

/** Имя пользователя MySQL */
define('DB_USER', 'root');

/** Пароль к базе данных MySQL */
define('DB_PASSWORD', '');

/** Имя сервера MySQL */
define('DB_HOST', 'localhost');

/** Кодировка базы данных для создания таблиц. */
define('DB_CHARSET', 'utf8mb4');

/** Схема сопоставления. Не меняйте, если не уверены. */
define('DB_COLLATE', '');

/**#@+
 * Уникальные ключи и соли для аутентификации.
 *
 * Смените значение каждой константы на уникальную фразу.
 * Можно сгенерировать их с помощью {@link https://api.wordpress.org/secret-key/1.1/salt/ сервиса ключей на WordPress.org}
 * Можно изменить их, чтобы сделать существующие файлы cookies недействительными. Пользователям потребуется авторизоваться снова.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '0T|S7~D#  ]Ie<D&U4tF(Bn.fldx7|~+2RAQ qx&7tXo*C?(cJ/9(KHW]/mdwhPe');
define('SECURE_AUTH_KEY',  ';->R.J+#5Jc%rZWO7u5zc~>WF1) ){SH!f9%X=7_YZp<NqUDFZK=GC c<.nW>v.-');
define('LOGGED_IN_KEY',    'R7rD;PWs8#Aqe+>is^;D0JrP|pvlbcdm`{P.X/9@#L::bBxx4hkZ@IcZPZNDfJl>');
define('NONCE_KEY',        'GkQ5^MY|!No,rKoF`!V5J=]jaPXa4zn5;bJB~QRa~VbE$@)}X[blIHKR[l(y1H;{');
define('AUTH_SALT',        '&/5dBeGk6y=$e5)A5lD2YUjUH0-.FXcIqxf$,~(?72ZQ5]R++v=f!2}E.p,sd1!}');
define('SECURE_AUTH_SALT', ':/dD)&FGn=,_Pz8j>-#iX9x f^uX#pTqrCQ5W3mzu6uvw#?V%g<(+BV&HarRrVEA');
define('LOGGED_IN_SALT',   'mg_#pKJbA3chADF>/Z(`}go}Z>ee|kCR&$XX2;EA@#^e?F2A#JA]]:)BrdPR5`5T');
define('NONCE_SALT',       'a{7b$F)nJ5HkvA:qiTw|hc` v%dwUE3Qf:-;Z0}h+p)y1r,!X;rNiuGuLA#$Epm&');

/**#@-*/

/**
 * Префикс таблиц в базе данных WordPress.
 *
 * Можно установить несколько сайтов в одну базу данных, если использовать
 * разные префиксы. Пожалуйста, указывайте только цифры, буквы и знак подчеркивания.
 */
$table_prefix  = 'wp_';

/**
 * Для разработчиков: Режим отладки WordPress.
 *
 * Измените это значение на true, чтобы включить отображение уведомлений при разработке.
 * Разработчикам плагинов и тем настоятельно рекомендуется использовать WP_DEBUG
 * в своём рабочем окружении.
 * 
 * Информацию о других отладочных константах можно найти в Кодексе.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* Это всё, дальше не редактируем. Успехов! */

/** Абсолютный путь к директории WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Инициализирует переменные WordPress и подключает файлы. */
require_once(ABSPATH . 'wp-settings.php');
