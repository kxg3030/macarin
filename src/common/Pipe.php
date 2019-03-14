<?php
namespace main\common;
class Pipe{
    public  $fifoPath;
    private $writePipe;
    private $readPipe;

    /**
     * Pipe constructor.
     * @param string $name
     * @param int $mode
     * @throws \Exception
     */
    public  function __construct($name = 'pipe', $mode = 0666){
        $fifoPath = "/tmp/$name." . posix_getpid();
        if (!file_exists($fifoPath)) {
            if (!posix_mkfifo($fifoPath, $mode)) {
                throw new \Exception("create {$name} pipe fail");
            }
        } else {
            throw new \Exception("pipe {$name} exist");
        }
        $this->fifoPath = $fifoPath;
    }

    public function openWrite(){
        $this->writePipe = fopen($this->fifoPath, 'w');
        if ($this->writePipe == null) {
            throw new \Exception("open pipe {$this->fifoPath} for write error.");
        }
        return true;
    }

    public function write($data){
        return fwrite($this->writePipe, $data);
    }

    public function writeAll($data){
        $writePipe = fopen($this->fifoPath, 'w');
        fwrite($writePipe, $data);
        fclose($writePipe);
    }

    public function closeWrite(){
        return fclose($this->writePipe);
    }

   public function openRead(){
        $this->readPipe = fopen($this->fifoPath, 'r');
        if ($this->readPipe == null) {
            throw new \Exception("open pipe {$this->fifoPath} for read error.");
        }
        return true;
    }

   public function read($byte = 1024){
        return fread($this->readPipe, $byte);
    }

   public function readAll(){
        $readPipe = fopen($this->fifoPath, 'r');
        $data = '';
        while (!feof($readPipe)) {
            $data .= fread($readPipe, 1024);
        }
        fclose($readPipe);
        return $data;
    }

   public function closeRead(){
        return fclose($this->readPipe);
    }

   public function rmPipe(){
        return unlink($this->fifoPath);
    }
}
