<?php
/**
 * Created by PhpStorm.
 * User: lance
 * Date: 2019/3/10
 * Time: 15:47
 */
namespace main;
use main\app\AppAbstract;
use main\app\AppInterface;
use main\common\Output;
use main\common\Pipe;
use main\server\Server;
use main\server\socket\Socket;


class App extends AppAbstract implements AppInterface {
    // 启动文件
    public static $startFile;
    // 是否是主进程
    public static $isMaster   = true;
    // 是否是manager进程
    public static $isManager  = false;
    // manager进程
    public static $managerPid = 0;
    // 是否是守护模式
    public static $isDaemon   = false;
    // 正在运行的子进程数
    public static $runningWorker = 0;
    // 允许运行子进程数量
    public static $workerNum  = 1;
    // 进程的workerId
    public static $workerId   = 1;
    // 子进程容器
    public static $childPro   = [];
    // 主进程号记录文件
    public static $pidFile    = SRC_PATH."/runtime/master.pid";
    // 重定向输出
    public static $outFile    = "/dev/null";
    // 类实例
    public static $instance   = null;
    // 主进程是否在运行
    public static $masterRun  = false;
    // 进程状态记录文件
    public static $statusFile = SRC_PATH."/runtime/status.txt";
    // 子进程启动回调
    public $onWorkStart;
    // 子进程退出回调
    public $onWorkStop;
    // 配置文件
    public $config = 0;
    // 管道
    public $pipe   = null;
    // 构造函数
    public function __construct(){
        self::$instance = $this;
    }

    /**
     * @throws \Exception
     */
    public static function run(){
        try{
            self::init();
            self::check();
            self::parseCommand();
            self::deamon();
            self::installSignal();
            self::resetStdFile();
            self::forWorkers();
            self::forkManager();
            self::monitorWorkers();
        }catch (\Exception $exception){
            self::exceptionAndStopAll($exception->getMessage());
        }catch (\Error $error){
            self::exceptionAndStopAll($error->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public static function init(){
        global $stdout,$stderr;
        $stdout = STDOUT;
        $stderr = STDERR;
        self::$instance->pipe = new Pipe("master");
    }

    public static function showDetail(){
        Output::writeLogo();
        Output::writeLine("master pid"   ,posix_getpid());
        Output::writeLine("managerPid"   ,self::$managerPid);
        Output::writeLine("workerNumb"   ,self::$workerNum);
        Output::writeLine("phpVersion"  ,phpversion());
        Output::writeLine("currentUsr"   ,posix_getpwuid(posix_geteuid())["name"]);
        Output::writeLine("whetherDem"   ,self::$isDaemon ? "true" : "false");
        Output::writeLine("listen  Ip"   ,Server::instance(new Socket())->getSocket()->host);
        Output::writeLine("listenPort"   ,Server::instance(new Socket())->getSocket()->port);
    }

    /**
     * stop all
     */
    public static function exceptionAndStopAll($message){
        fwrite(STDOUT,$message.PHP_EOL);
        foreach (self::$childPro as $pid => $value){
            posix_kill($pid,SIGTERM);
        }
        $masterPid = posix_getpid();
        posix_kill($masterPid,SIGTERM);
    }

    /**
     * @return bool
     * extension check
     */
    public static function check(){
        if(version_compare(PHP_VERSION,"7.0","<")){
            Output::writeAndExit("php version must more than 7.0");
        }
        if(php_sapi_name() != "cli"){
            Output::writeAndExit("must in cli mode");
        }
        if(extension_loaded("pcntl") == false){
            Output::writeAndExit("pcntl extension required...");
        }
        if(extension_loaded("posix") == false){
            Output::writeAndExit("posix extension required...");
        }
        if(posix_getuid() != 0){
            Output::writeAndExit("root permission required...");
        }
    }

    /**
     * check master pid file
     */
    public static function checkMasterPidFile():int {
        if(file_exists(self::$pidFile)){
            $masterPid = file_get_contents(self::$pidFile);
            if($masterPid){
                if(posix_kill($masterPid,SIG_DFL)){
                    return $masterPid;
                }
            }
        }
        Output::writeAndExit("master process is not run");
    }

    /**
     * start
     */
    public static function start($reload = false){
        global $argv;
        $commandDemon    = array_shift($argv);
        self::$isDaemon  = $commandDemon == "-d" ? true : false;
        if(file_exists(self::$pidFile)){
            $masterPid = file_get_contents(self::$pidFile);
            if($masterPid){
                $masterStatus = posix_kill($masterPid,SIG_DFL);
                if($reload == false){
                    for($times = 0; $times <= 3; $times ++){
                        if($masterStatus == true){
                            Output::writeAndExit("master process already running");
                        }
                        sleep(1);
                    }
                }
            }
        }
    }

    /**
     * 保存主进程的进程ID
     */
    public static function saveMasterPid(){
        if(file_exists(self::$pidFile) == false){
            touch(self::$pidFile);
        }
        file_put_contents(self::$pidFile,posix_getpid());
    }

    /**
     * stop
     */
    public static function stop(){
        $masterPid = self::checkMasterPidFile();
        if($masterPid){
            posix_kill($masterPid,SIGTERM);
            while ($masterPid && posix_kill($masterPid, SIG_DFL)) {
                usleep(300000);
            }
        }
    }

    /**
     * reload
     * 这里会重启一个全新的master(self::masterRun、stdout、stdin、stderr都是初始值)
     * posix_getpid返回的是这个全新进程的ID,所以不能用这个方法获取旧master进程的进程ID
     */
    public static function restart(){
        $masterPid = self::checkMasterPidFile();
        if($masterPid){
            Output::writeLog("start reload master process");
            self::stop();
            sleep(3);
            self::start(true);
        }
    }

    /**
     * 查看所有进程的进程状态
     */
    public static function status(){
        //查看状态
        if(file_exists(static::$statusFile)){
            //先删除status文件
            @unlink(static::$statusFile);
        }
        $masterPid = self::checkMasterPidFile();
        posix_kill($masterPid,SIGUSR1);
        usleep(300000);
        fwrite(STDOUT,file_get_contents(static::$statusFile));
    }

    /**
     * 平滑重启子进程
     */
    public static function reload(){
        $masterPid = self::checkMasterPidFile();
        if($masterPid){
            posix_kill($masterPid,SIGHUP);
        }
    }

    /**
     * 重定向标准输出
     */
    public static function resetStdFile(){
        if(self::$isDaemon){
            global $stdout,$stderr;
            $handle = fopen(self::$outFile,"a");
            if($handle){
                fclose(STDOUT);
                fclose(STDERR);
                $stdout = fopen(self::$outFile,"a");
                $stderr = fopen(self::$outFile,"a");
            }else{
                Output::writeLog("recover standard output fail");
            }
        }
    }

    /*
     * deamon
     */
    public static function deamon(){
        if(self::$isDaemon && self::$isMaster){
            $pid = pcntl_fork();
            if($pid == -1){
                Output::writeAndExit("fork error");
            }
            if($pid > 0){
                exit(0);
            }
            if(posix_setsid() == -1){
                Output::writeAndExit("setsid error");
            }
            $pid = pcntl_fork();
            if($pid == -1){
                Output::writeAndExit("deamon error");
            }
            if($pid > 0){
                exit(0);
            }
        }
    }

    /*
     * set title
     */
    public static function setTitle($title){
        cli_set_process_title($title);
    }

    /**
     * parse command
     */
    public static function parseCommand(){
        global $argv;
        self::$startFile = array_shift($argv);
        $commandInput    = array_shift($argv);
        switch ($commandInput){
            case self::APP_START:
                self::start();
                break;
            case self::APP_STOP:
                self::stop();
                exit(0);
                break;
            case self::APP_RESTART:
                self::restart();
                break;
            case self::APP_STATUS:
                self::status();
                exit(0);
                break;
            case self::APP_RELOAD:
                self::reload();
                exit(0);
                break;
            case self::APP_HELP:
                Output::writeHelp();
                break;
            default:
                Output::writeWelcome();
        }
    }

    /**
     * install signal
     */
    public static function installSignal(){
        pcntl_signal(SIGUSR1,"self::signalHandle",false);
        pcntl_signal(SIGTERM,"self::signalHandle",false);
        pcntl_signal(SIGINT, "self::signalHandle",false);
        pcntl_signal(SIGCHLD,"self::signalHandle",false);
        pcntl_signal(SIGHUP, "self::signalHandle",false);
    }

    /**
     * @param string $signal
     * @return bool
     * recover child process
     */
    public static function setProcessRunStatus($status){
        $statusLog = $status ? "running" : "terminal";
        if(self::$isMaster){
            Output::writeLog("master process status :{$statusLog},child process count:".count(self::$childPro));
            self::$masterRun = $status;
            if(self::$childPro){
                foreach (self::$childPro as $pid => $detail){
                    posix_kill($pid,SIGTERM);
                    sleep(1);
                }
            }
            self::unlinkPipeFile();
            if(file_exists(Output::$logFile)){
                @unlink(Output::$logFile);
            }
            if(file_exists(self::$pidFile)){
                @unlink(self::$pidFile);
            }
            exit(0);
        }
        if(self::$isManager){
            Server::instance()->getSocket()->closeAll();
            if(self::$childPro){
                foreach (self::$childPro as $pid => $detail){
                    posix_kill($pid,SIGKILL);
                    sleep(1);
                }
                while (true){
                    $pid = pcntl_wait($status,WUNTRACED);
                    if($pid > 0){
                        unset(self::$childPro[$pid]);
                        if(count(self::$childPro) <= 0){
                            break;
                        }
                    }
                    sleep(1);
                }
            }
            self::unlinkPipeFile();
            exit(0);
        }
        if (self::$instance->onWorkStop) {
            call_user_func(self::$instance->onWorkStop, self::$instance);
        }
        self::unlinkPipeFile();
        exit(0);
    }

    public static function unlinkPipeFile(){
        self::$instance->pipe->rmPipe();
    }

    /**
     * @param $signalDetail
     * clear static params
     */
    public static function clearArrayByPid($signalDetail){
        if(self::$isMaster){
            Output::writeLog("child process {$signalDetail['pid']} exit and master process delete record");
        }
    }

    /**
     * @throws \Exception
     */
    public static function forWorkers(){
        for ($worker = 1; $worker <= self::$workerNum; $worker ++){
            self::forkOneChild($worker);
        }
    }

    /**
     * @param $workerId
     * @throws \Exception
     */
    public static function forkOneChild($workerId){
        $pid = pcntl_fork();
        if($pid == -1){
            Output::writeAndExit("fork one child process error");
        }
        if($pid == 0){
            self::setTitle("worker-process-{$workerId}(index.php)");
            self::$instance->pipe = new Pipe("child");
            self::$workerId = $workerId;
            self::$isMaster = false;
            self::$instance->task();
        }
        self::$runningWorker ++;
        self::$childPro[$pid] = [
            "pid"      => $pid,
            "tsk"      => "self::task",
            "workerId" => $workerId,
            "role"     => "worker"
        ];
    }

    /*
     * 重新加载配置文件
     */
    public static function resetConfig(){
        self::$instance->config = time();
        if(self::$childPro){
            foreach (self::$childPro as $pid => $value){
                posix_kill($pid,SIGHUP);
            }
        }
        Output::writeLog("child process is reload and config have been changed");
    }

    /**
     * @param $signal(SIGKILL与SIGSTOP无法忽略、阻塞、捕捉)
     */
    public static function signalHandle($signalNo,$signalDetail){
       switch ($signalNo){
           case SIGTERM:
               self::setProcessRunStatus(false);
               break;
           case SIGCHLD:
               self::clearArrayByPid($signalDetail);
               break;
           case SIGINT:
               self::setProcessRunStatus(false);
               break;
           case SIGUSR1:
               self::writeStatus();
               break;
           case SIGHUP:
               self::resetConfig();
               break;
           default:

       }
    }

    /**
     * 输出进程状态到文件
     */
    public static function writeStatus(){
        if(self::$isMaster){
            $masterPid = self::checkMasterPidFile();
            if($masterPid){
                Output::writeStatus(self::$statusFile,"master process {$masterPid} is running now");
                foreach(static::$childPro as $pid => $value){
                    posix_kill($pid,SIGUSR1);
                }
            }
        }else{
            $currentPid = posix_getpid();
            if(posix_kill($currentPid,SIG_DFL)){
                Output::writeStatus(self::$statusFile,"child process {$currentPid} is running now");
            }else{
                Output::writeStatus(self::$statusFile,"child process {$currentPid} is died");
            }
        }
    }

    /**
     * 子进程执行任务
     */
    public function task(){
        if ($this->onWorkStart) {
            try {
                call_user_func($this->onWorkStart, $this);
            } catch (\Exception $exception) {
                Output::writeAndExit($exception->getMessage());
            } catch (\Error $error) {
                Output::writeAndExit($error->getMessage());
            }
        }
        while (true) {
            pcntl_signal_dispatch();
            Output::writeLog($this->config ++);
            sleep(5);
            pcntl_signal_dispatch();
        }
    }

    /**
     * @throws \Exception
     */
    public static function forkManager(){
        $pid = pcntl_fork();
        if($pid == 0){
            Event::instance(Server::instance(new Socket())->getSocket())->getSocket()->start();
            self::setTitle("manager-process(index.php)");
            self::$instance->pipe = new Pipe("manager");
            self::$isMaster = false;
            self::$isManager= true;
            self::$childPro = [];
            while (true){
                pcntl_signal_dispatch();
                $connect = @socket_accept(Server::instance()->getSocket()->server);
                if($connect){
                    Server::instance()->getSocket()->addConnect($connect);
                    Server::instance()->getSocket()->callback  ($connect,"onConnect");
                    Server::instance()->getSocket()->readData($connect);
                }
                pcntl_signal_dispatch();
            }
        }
        self::$managerPid     = $pid;
        self::$childPro[$pid] = [
            "pid"      => $pid,
            "tsk"      => "null",
            "workerId" => 0,
            "role"     => "manager"
        ];
    }

    /**
     * @throws \Exception
     * 主进程监控
     */
    public static function monitorWorkers(){
        self::setTitle("master-process(index.php)");
        self::saveMasterPid();
        self::showDetail();
        self::$masterRun = true;
        while (true) {
            pcntl_signal_dispatch();
            $status = 0;
            $pid    = pcntl_wait($status, WUNTRACED);
            pcntl_signal_dispatch();
            if ($pid > 0) {
                if (static::$masterRun) {
                    $workerId = self::$childPro[$pid]["workerId"];
                    unset(self::$childPro[$pid]);
                    self::forkOneChild($workerId);
                }
            }
        }
    }
}
