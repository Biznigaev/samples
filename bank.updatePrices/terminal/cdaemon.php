<?php

class CDaemon
{
    const NAME = 'daemon';
    const PATH = __DIR__;
    //    путь к файлу с логом
    private static $logPath;

    public function __construct()
    {
        self::$logPath = $_SERVER['DOCUMENT_ROOT'].'/bitrix/components/crediteurope/terminal/logs/daemon.log';
    }
    public function isActive()
    {
        return (bool)((int) trim(shell_exec('pidof '.self::NAME)) > 0);
    }
    public function GetPid()
    {
        if (!self::isActive())
        {
            return -1;
        }
        return IntVal(trim(shell_exec('pidof '.self::NAME)));
    }
    public function Start($intervalList)
    {
        //    получаем порождённые ранее процессы и удаляем
        $plist = trim(shell_exec('pidof '.self::NAME));
        if (strlen($plist) > 0)
        {
            if (strpos($plist,' ') !== false)
            {
                $plist = '{'.str_replace(' ',',',$plist).'}';
            }
            shell_exec("kill -15 {$plist}");
        }
        //    запускаем процесс
        shell_exec(self::PATH.'/'.self::NAME.' '.implode(' ',array_values($intervalList)));
        $pid = self::GetPid();
        if ($pid > 0)
        {
            self::AddMessage2Log(date('d.m.Y H:i:s').': STARTED: '.self::PATH.'/'.self::NAME.' '.implode(' ',array_values($intervalList)));
        }
        return $pid;
    }
    public function Stop()
    {
        if (self::isActive())
        {
            //    запись момента остановки демона по причине таймаута в общий лог приложения
            self::AddMessage2Log(date('d.m.Y H:i:s').': STOPPED');
            shell_exec("kill -15 ".self::GetPID());
        }
        else
        {
            return -1;
        }
    }
    public function AddMessage2Log($sText)
    {
        if ($fp = fopen(self::$logPath, "ab+"))
        {
            if (flock($fp, LOCK_EX))
            {
                fwrite($fp, $sText."\n");
                fwrite($fp, "----------\n");
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }
}