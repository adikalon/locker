<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Hellpers\Locker;

/*
|------------------------------------------------------------------------------
| Установка локера
|------------------------------------------------------------------------------
|
| Блокировка на повторный запуск скрипта осуществляется в одну строчку. Метод 
| Locker::set() принимает 4 необязательных параметра:
|
| 1. (string) Абсолютный путь к папке для хранения lock-файла (по умолчанию
| будет выбрат каталог исполняемого файла);
| 2. (string) Имя для lock-файла (по умолчанию лок файл получит название
| исполняемого файла с расширением lock);
| 3. (bool) Что делать при повторном запуске скрипта: обрывать выполнение по
| exit() или бросать Exception() (по умолчанию - exit(), т.е. false);
| 4. (bool) Записывать id процесса в lock-файл (только для unix-подобных ОС) (по
| умолчанию - true);
|
| Метод ничего не возвращает.
|
*/
Locker::set(__DIR__, 'test.lock', false, true);



/* ========================================================================== */



/*
|------------------------------------------------------------------------------
| Killer для запущенного процесса (только для unix-подобных ОС)
|------------------------------------------------------------------------------
|
| Если локер был установлен с разрешением на запись идентификатора процесса в
| локирующий файл, тогда запущенный процесс можно убить методом Locker::kill(),
| передав в него абсолютный путь к lock-файлу.
|
| Возаращает true, если удалось убить процесс и false - если не удалось.
|
*/
Locker::kill(__DIR__ . DIRECTORY_SEPARATOR . 'test.lock');