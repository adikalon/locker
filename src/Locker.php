<?php

namespace Hellpers;

use Exception;

class Locker
{
    /**
     * @var resource|null Ресурс lock-файла
     */
    private static $resource = null;

    /**
     * @var bool Защита от повторной установки 
     */
    private static $working = false;

    /**
     * @var string Абсолютный путь к lock-файлу 
     */
    private static $file = '';

    /**
     * Из класса нельзя создавать объект
     */
    private function __construct() {}

    /**
     * Установка локера
     * 
     * @param string|null $path (optional) Абсолютный путь к папке для хранения
     * lock-файла
     * @param string|null $file (optional) Имя для lock-файла
     * @param bool $exception (optional) Бросать исключение/прерывать выполнение
     * скрипта
     * @param bool $pid (optional) Записывать id процесса в lock-файл (только
     * для unix-подобных ОС)
     * @param bool $signal (optional) Обрабатывать сигналы - да/нет, true/false
     * @return void
     * @throws Exception
     */
    public static function set(
        ?string $path = null,
        ?string $file = null,
        bool $exception = false,
        bool $pid = true,
        bool $signal = false
    ): void
    {
        $name = '';

        if (self::$working) {
            return;
        } else {
            self::$working = true;
        }

        if (is_null($path)) {
            $path = realpath($_SERVER['SCRIPT_FILENAME']);
            $name = preg_quote(basename($path));
            $path = preg_replace("/$name$/ui", '', $path);
        }

        if (is_null($file)) {
            $file = basename($_SERVER['SCRIPT_FILENAME'], '.php') . '.lock';
        }

        if (!file_exists($path) and !mkdir($path, 0777, true)) {
            throw new Exception("Не удалось создать путь: $path");

            exit;
        }

        self::$file = realpath($path) . DIRECTORY_SEPARATOR . $file;

        if ((self::$resource = fopen(self::$file, 'cb')) === false) {
            throw new Exception('Не удалось открыть lock-файл на запись');

            exit;
        }

        if (!is_resource(self::$resource)) {
            throw new Exception('Ошибка получения ресурса');

            exit;
        }

        if (!flock(self::$resource, LOCK_EX|LOCK_NB)) {
            if ($exception) {
                throw new Exception('Запуск копии');
            } else {
                exit;
            }
        } else {
            if (!ftruncate(self::$resource, 0)) {
                throw new Exception('Не удалось обрезать lock-файл');

                exit;
            }

            register_shutdown_function([__CLASS__, 'remove']);

            if (mb_stristr(php_uname('s'), 'win') === false) {
                if ($pid) {
                    if (fwrite(self::$resource, getmypid()) === false) {
                        throw new Exception(
                            'Не удалось записать идентификатор процесса'
                        );

                        exit;
                    } else {
                        fflush(self::$resource);
                    }
                }

                if ($signal) {
                    pcntl_signal(SIGINT, [__CLASS__, 'remove']);
                    pcntl_signal(SIGQUIT, [__CLASS__, 'remove']);
                    pcntl_signal(SIGTSTP, [__CLASS__, 'remove']);
                }
            }
        }

        unset($path, $file, $exception, $pid, $name);
    }

    /**
     * Callback для сигналов. Удаляет lock-файл по завершению/обрыву выполнения
     * скрипта
     * 
     * @param int $no (optional) Номер обрабатываемого сигнала
     * @param array|null $info (optional) Информация
     * @return void
     * @throws Exception
     */
    public static function remove(int $no = 0, ?array $info = null): void
    {
        if (
            file_exists(self::$file)
            and is_file(self::$file)
            and !unlink(self::$file)
        ) {
            throw new Exception('Не удалось удалить lock-файл: ' . self::$file);
        } elseif (is_resource(self::$resource)) {
            flock(self::$resource, LOCK_UN);
            fclose(self::$resource);
        }

        unset($no, $info);

        exit;
    }

    /**
     * Убить процесс по lock-файлу (только для unix-подобных ОС)
     * 
     * @param string $path Абсолютный путь к lock-файлу
     * @return bool Удалось/не удалось убить процесс
     * @throws Exception
     */
    public static function kill(string $path): bool
    {
        $pid = '';

        if (mb_stristr(php_uname('s'), 'win') !== false) {
            unset($path, $pid);

            return false;
        }

        if (!file_exists($path) or !is_file($path)) {
            unset($path, $pid);

            return false;
        }

        $pid = file_get_contents($path);
        $pid = trim($pid);

        if (!is_numeric($pid)) {
            unset($path, $pid);

            return false;
        }

        $pid = (int)$pid;

        if (!posix_kill($pid, SIGINT)) {
            if (posix_kill($pid, SIGKILL)) {
                if (!unlink($path)) {
                    throw new Exception("Не удалось удалить lock-файл: $path");
                }

                unset($path, $pid);

                return true;
            } else {
                unset($path, $pid);

                return false;
            }
        } else {
            unset($path, $pid);

            return true;
        }
    }
}
