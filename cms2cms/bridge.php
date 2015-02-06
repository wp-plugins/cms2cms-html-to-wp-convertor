<?php
/**
 * This script is necessary setup automated data exchange 
 * between Your/Merchant site and CMS2CMS.
 *
 * Please carefully follow steps below.
 *
 * Requirements
 * ===========================================================================
 * PHP 4.3.x - 5.x
 * PHP Extensions: CURL (libcurl), GZip (zlib)
 *
 * Installation Instructions
 * ===========================================================================
 * 1. Extract files from archive and upload "cms2cms" folder into your site 
 *    root catalog via FTP.
 *    Example how to upload: "http://www.yourstore.com/cms2cms"
 * 2. Make "cms2cms" folder writable (set the 777 permissions, "write for all")
 * 3. Press Continue in Migration Wizard at CMS2CMS to check compatibility
 *    You are done.
 *
 * If you have any questions or issues
 * ===========================================================================
 * 1. Check steps again, startign from step 1
 * 2. See Frequently Asked Questions at http://cms2cms.com/faq
 * 3. Send email (support@cms2cms.com) to CMS2CMS support requesting help.
 * 4. Add feedback on http://cms2cms.betaeasy.com/
 *
 * Most likely you uploaded this script into wrong folder 
 * or misstyped the site address.
 *
 * DISCLAIMER
 * ===========================================================================
 * THIS SOFTWARE IS PROVIDED BY CMS2CMS ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL CMS2CMS OR ITS
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * CMS2CMS by MagneticOne
 * (c) 2010 MagneticOne.com <contact@cms2cms.com>
 */
?><?php
@set_time_limit(0);
@ini_set('max_execution_time', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_WARNING);
@ini_set('display_errors', '1');


if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = & $process[$key][stripslashes($k)];
            }
            else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

header('X-msa-localtime: ' . time());
$loader = Bridge_Loader::getInstance();

$app = new Bridge_Dispatcher();
$app->dispatch();

$response =  Bridge_Response::getInstance();
$response->sendResponse();
?><?php

/**
 * This class load and locate some libs
 * Also can determine shopping cart folder structure
 *
 */
class Bridge_Loader
{
    /**
     * @var Bridge_Module_Cms_Abstract
     */
    protected $cmsInstance;

    /**
     * @var Bridge_Fs
     */
    protected $fs;

    protected $bridgeDir;

    protected $rootDir;

    private function __construct()
    {
        $currentDir = realpath(dirname(__FILE__));
        $this->bridgeDir = $currentDir . '/';
        $this->rootDir = realpath($currentDir . str_repeat('/..', $this->getRootLevel()));
        $this->fs = new Bridge_Fs($this);
    }

    public function getBridgeDir()
    {
        return $this->bridgeDir;
    }

    public function getBridgeVersion()
    {
        $bridgeVersion = '';
        $versionFile = $this->getBridgeDir() . 'version.txt';
        if (file_exists($versionFile)) {
            $bridgeVersion = file_get_contents($versionFile);
        }

        return $bridgeVersion;
    }

    public function getRootLevel()
    {
        $rootLevel = 1;
        $levelFile = $this->getBridgeDir() . 'root_level.txt';
        if (file_exists($levelFile)) {
            $rootLevel = file_get_contents($levelFile);
        }

        return $rootLevel;
    }

    public function getAccessKey()
    {
        $loader = Bridge_Loader::getInstance();
        $dir = $loader->getBridgeDir();
        $keyFile = $dir . DIRECTORY_SEPARATOR . 'key.php';

        if (!file_exists($keyFile)) {
            $currentCms = $loader->getCmsInstance();
            $key = $currentCms->getAccessKey();
            if (empty($key)) {
                Bridge_Exception::ex('Access Key is empty', 'empty_hash');
            }
            define('CMS2CMS_ACCESS_KEY', $key);
        }
        else {
            /** @noinspection PhpIncludeInspection */
            include $keyFile;
        }

        $accessKey = false;
        if (defined('CMS2CMS_ACCESS_KEY')) {
            $accessKey = constant('CMS2CMS_ACCESS_KEY');
        }

        if ($accessKey == false || strlen($accessKey) != 64) {
            Bridge_Exception::ex('Access Key is corrupted', 'invalid_hash');
        }

        return $accessKey;
    }

    public function getCmsInstance()
    {
        if ($this->cmsInstance === null) {
            $this->cmsInstance = $this->detectCms();
        }

        return $this->cmsInstance;
    }

    public function getFs()
    {
        return $this->fs;
    }

    public function isSafeModeEnabled()
    {
        return !!ini_get('safe_mode');
    }

    /**
     * Get current directory path in linux notation
     *
     * @return string
     */
    function getCurrentPath()
    {
        return $this->rootDir;
    }

    /**
     * Check if path in open base dir
     *
     * @param string $dir
     *
     * @return bool
     */
    function isInOpenBaseDir($dir)
    {
        $basedir = ini_get('open_basedir');
        if ($basedir == false) {
            return true;
        }
        // exception for apache constant VIRTUAL_DOCUMENT_ROOT.
        // See http://wiki.preshweb.co.uk/doku.php?id=apache:securemassvhosting
        if (strpos($basedir, 'VIRTUAL_DOCUMENT_ROOT') !== false) {
            $basedir = str_replace('VIRTUAL_DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'], $basedir);
        }

        if (strpos($basedir, PATH_SEPARATOR) !== false) {
            $arrBaseDir = explode(PATH_SEPARATOR, $basedir);
        }
        elseif (strpos($basedir, ':') !== false) {
            $arrBaseDir = explode(':', $basedir);
        }
        else {
            $arrBaseDir = array($basedir);
        }
        $path = $this->fs->realpath($dir) . '/';
        if ($path == false) {
            return false;
        }

        foreach ($arrBaseDir as $base) {
            if (strpos($path, $this->fs->realpath($base)) !== false) {
                return true;
            }
        }

        return false;
    }

    function getSupportedCmsModules()
    {
        $modules = array(
            'WordPress' => array(
                'WordPress3'
            ),
            'Drupal' => array(
                'Drupal7',
                'Drupal6',
                'Drupal5'
            ),
            'Joomla' => array(
                'Joomla15',
                'Joomla17',
                'Joomla25'
            ),
            'Typo3' => array(
                'Typo34',
                'Typo36'
            ),
            'phpBb' => array(
                'phpBb',
            ),
            'Vbulletin' => array(
                'Vbulletin4',
            ),
            'IPBoard' => array(
                'IPBoard',
            ),
            'SMF' => array(
                'SMF',
            ),
            'MyBB' => array(
                'MyBB',
            ),
            'b2evolution' => array(
                'b2evolution',
            ),
            'dle' => array(
                'dle',
            ),
            'e107' => array(
                'e107',
            )
        );


        return $modules;
    }

    protected function getCmsModuleClassName($cmsType, $module)
    {
        return 'Bridge_Module_Cms_' . ucfirst($cmsType) . '_' . ucfirst($module);
    }

    protected function getCmsModuleClass($cmsType, $module)
    {
        $className = $this->getCmsModuleClassName($cmsType, $module);
        if (!class_exists($className)) {
            return false;
        }

        return $className;
    }

    protected function detectCmsModule($cmsType, $modules)
    {
        $detectedCmsModule = null;
        foreach ($modules as $module) {
            $className = $this->getCmsModuleClass($cmsType, $module);
            if (!$className) {
                continue;
            }

            /**@var $cmsModuleInstance Bridge_Module_Cms_Abstract */
            $cmsModuleInstance = new $className();
            if ($cmsModuleInstance->detect()) {
                $detectedCmsModule = $cmsModuleInstance;
                break;
            }
        }

        return $detectedCmsModule;
    }

    /**
     * Suggest, a cms  type by files
     *
     * @return string cms cart type could be 'WordPress', 'Joomla'
     */
    function detectCms()
    {
        $detectedModule = null;
        $cmsModules = $this->getSupportedCmsModules();
        foreach ($cmsModules as $cmsType => $modules) {
            $detectedModule = $this->detectCmsModule($cmsType, $modules);
            if ($detectedModule !== null) {
                break;
            }
        }

        if ($detectedModule === null) {
            Bridge_Exception::ex('Can not detect cms', 'detect_error');
        }

        return $detectedModule;
    }

    /**
     * Create new Bridge_Loader instance and return created object reference
     * If object was created, this function will return that object reference
     *
     * @return Bridge_Loader
     */
    public static function getInstance()
    {
        static $class;
        if ($class == null) {
            $class = new Bridge_Loader();
        }

        return $class;
    }

}

?><?php
class Bridge_Fs
{

    /**
     * @var Bridge_Loader
     */
    protected $loader;

    public function __construct(Bridge_Loader $loader)
    {
        $this->loader = $loader;
    }

    protected function getLocalPath($host, $url)
    {
        $host = urldecode($host);
        $url = urldecode($url);

        $urlHost = parse_url($host, PHP_URL_HOST);
        $urlPath = parse_url($host, PHP_URL_PATH);

        $urlHost = str_replace('~', '\~', $urlHost);
        $urlPath = str_replace('~', '\~', $urlPath);

        if (strpos($urlHost, 'www.') === 0){
            $urlHost = substr($urlHost, 4);
        }

        $pattern = sprintf('~https?://(www\.)?%s%s~', $urlHost, $urlPath);
        $path = preg_replace($pattern, '', $url);

        return $path;
    }

    public function getLocalAbsPath($host, $url)
    {
        $path = $this->getLocalPath($host, $url);
        $dir = $this->loader->getCurrentPath();
        $absPath = $dir . DIRECTORY_SEPARATOR . $path;

        return $absPath;
    }

    public function getLocalRelativePath($path)
    {
        $currentPath = $this->loader->getCurrentPath();
        if (strpos($path, $currentPath) !== 0){
            return $path;
        }

        $path = substr($path, strlen($currentPath));

        return $path;
    }

    public function createPathIfNotExists($path)
    {
        $dir = dirname($path);
        if (!file_exists($dir)
            && !mkdir($dir, 0777, true)
        ){
            throw new Exception(sprintf('Can not create target dir %s', $dir));
        }
    }

    /**
     * Wrapper for realpath() function (with Windows support)
     *
     * @param string $path
     *
     * @return string Normalized path
     */
    function realpath($path)
    {
        $path = @realpath($path);
        if ($path == false) {
            return false;
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * Get array of subdirectories
     *
     * @param string $dir Path to directory
     *
     * @param bool $self
     * @return array
     */
    function getDirList($dir, $self = false)
    {
        if ($self) {
            $fileList = array('.');
        }
        else {
            $fileList = array();
        }

        if (!$this->loader->isInOpenBaseDir($dir)) {
            return $fileList;
        }

        if (PHP_OS != 'Linux' || ((is_link($dir) || is_dir($dir)) && is_readable($dir))) {
            if (($dh = @opendir($dir)) !== false) {
                while (($file = readdir($dh)) !== false) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    if (!@is_link($dir . '/' . $file) && @is_dir($dir . '/' . $file)) {
                        $fileList[] = $file;
                    }
                }
                closedir($dh);
            }
        }

        return $fileList;
    }

    function is_writable($path)
    {
        if ($path{strlen($path) - 1} == '/') {
            return $this->is_writable($path . uniqid(mt_rand()) . '.tmp');
        }

        if (file_exists($path)) {
            if (!($f = @fopen($path, 'r+'))) {
                return false;
            }
            fclose($f);

            return true;
        }

        if (!($f = @fopen($path, 'w'))) {
            return false;
        }
        fclose($f);
        unlink($path);

        return true;
    }

    /**
     * Make string point to upper directory
     * Ex: /etc/smb -> /etc
     *
     * @param string $dirName
     *
     * @return string
     */
    function chdirup($dirName)
    {
        return substr($dirName, 0, strrpos($dirName, '/'));
    }

}
?><?php
class Bridge_Response_Null
{
    function openNode()
    {
    }

    function closeNode()
    {
    }

    function closeResponseFile()
    {
    }

    function sendResponse()
    {
    }

    function sendData($data)
    {
    }
}

class Bridge_Response_Memory
{
    var $hFile;
    var $response;

    var $openNodes = array();

    function Bridge_Response_Memory()
    {
        $this->response = '<response>';
        $this->openNodes = array();
    }

    function openNode($nodeName)
    {
        $this->response .= '<' . $nodeName . '>';
        array_push($this->openNodes, $nodeName);
    }

    function closeNode()
    {
        $nodeName = array_pop($this->openNodes);
        if ($nodeName == false) {
            Bridge_Exception::ex('Trying to close response node but no does are opened', 0);
        }
        $this->response .= '</' . $nodeName . '>';
    }

    function sendData($data)
    {
        $this->response .= '<![CDATA[' . $data . ']]>';
    }

    function sendNode($nodeName, $data)
    {
        $this->openNode($nodeName);
        $this->sendData($data);
        $this->closeNode($nodeName);
    }

    function closeResponseFile()
    {
        $this->response .= '</response>';
    }

    function sendResponse()
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 0);
        }

        $this->closeResponseFile();
        if(function_exists('gzencode')){
            $responseData = gzencode($this->response);
            header('Content-Encoding: gzip');
            header('Content-Type: application/x-gzip');
            header('Content-Length: ' . strlen($responseData));
        }
        else{
            $responseData = $this->response;
            header('Content-Encoding: text');
            header('Content-Type: application/plain-text');
        }

        echo $responseData;
    }
}

class Bridge_Response
{

    private function __construct()
    {

    }

    /**
     * Create a singleton instance of Bridge_Response
     *
     * @param string $classname
     * @return Bridge_Response
     */
    public static function  getInstance($classname = '')
    {
        static $obj;
        if ($obj === null || ($classname != '' && get_class($obj) != $classname)) {
            if ($classname == '') {
                $classname = 'Bridge_Response_Memory';
            }

            $obj = new $classname();
        }

        return $obj;
    }

    static function openNode($nodeName = '')
    {
        $obj = Bridge_Response::getInstance();
        $obj->openNode($nodeName);
    }

    static function closeNode()
    {
        $obj = Bridge_Response::getInstance();
        $obj->closeNode();
    }

    function sendData($data)
    {
        $obj = Bridge_Response::getInstance();
        $obj->sendData($data);
    }

   static function getFileHandler()
    {
        $obj = Bridge_Response::getInstance();

        /** @noinspection PhpUndefinedFieldInspection */

        return $obj->hFile;
    }

   static  function disable()
    {
        Bridge_Response::getInstance('Bridge_Response_Null');
    }

    function sendResponse()
    {
        $obj = Bridge_Response::getInstance();
        $obj->sendResponse();
    }
}

?><?php

class Bridge_Includer {

    public static function backupEnvironment()
    {
        $environment = array(
            'globals' => $GLOBALS,
            'session' => $_SESSION,
            'server' => $_SERVER,
            'env' => $_ENV,
            'cookie' => $_COOKIE,
            'request' => $_REQUEST,
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'general' => array(
                'bufferCount' => ob_get_level()
            )
        );
        return $environment;
    }

    public static function safeInclude($fileName, $constants = array(), $variables = array(), $functions = array())
    {

        if (function_exists('php_check_syntax')){
            if (!php_check_syntax($fileName)){
                return false;
            }
        }
        //TODO think about it ?!
        /*
        else {
            //http://bytes.com/topic/php/answers/538287-check-syntax-before-include
            $code = file_get_contents($fileName);
            $code = preg_replace('/(^|\?>).*?(<\?php|$)/i', '', $code);
            $f = @create_function('', $code);
            $result = !empty($f);
        }
        */
        
        if (! file_exists($fileName)){
            return false;
        }

        ob_start();
        /** @noinspection PhpIncludeInspection */
        include($fileName);
        ob_clean();

        //TODO think about moving "general" environment variables to sub-array
        $environment = array(
            'globals' => $GLOBALS,
            'session' => $_SESSION,
            'server' => $_SERVER,
            'env' => $_ENV,
            'cookie' => $_COOKIE,
            'request' => $_REQUEST,
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'constants' => array(),
            'variables' => array(),
            'functions' => array()
        );

        $constantsValues = array();
        foreach($constants as $constantName){
            if (defined($constantName)){
                $constantsValues[$constantName] = constant($constantName);
            }
            else {
                $constantsValues[$constantName] = null;
            }
        }
        $environment['constants'] = $constantsValues;

        $variablesValues = array();
        foreach($variables as $variableName){
            $variablesValues[$variableName] = $$variableName;
        }
        $environment['variables'] = $variablesValues;

        $functionsCallbacks = array();
        foreach($functions as $functionName){
            if (function_exists($functionName)){
                $functionsCallbacks[$functionName] = $functionName;
            }
        }
        $environment['functions'] = $functionsCallbacks;
        $environment['general'] = array();
        $environment['general']['bufferCount'] = ob_get_level();
        return $environment;
    }

    public static function restoreEnvironment($environment)
    {
        $GLOBALS = $environment['globals'];
        $_SESSION = $environment['session'];
        $_SERVER = $environment['server'];
        $_ENV = $environment['env'];
        $_COOKIE = $environment['cookie'];
        $_REQUEST = $environment['request'];
        $_GET = $environment['get'];
        $_POST = $environment['post'];
        $_FILES = $environment['files'];

        $buffCount = (int)$environment['general']['bufferCount'];
        for($i = ob_get_level(); $i > $buffCount; $i--){
            ob_get_clean();
        }
    }

    public static function initCookies($data, $serialized = false)
    {
        if ($serialized){
            $data = unserialize($data);
        }

        if (!is_array($data)){
            return false;
        }

        $_COOKIE = $data;
        return true;
    }

    public static function initSession($data, $serialized = false)
    {
        session_start();

        if ($serialized){
            session_decode($data);
        }
        else {
            $_SESSION = $data;
        }

        return is_array($_SESSION);
    }

    public static function parseConfigFile($filename)
    {
        $fileData = file_get_contents($filename);
        $lines = explode("\n", $fileData);
        $lines = array_filter($lines);
        $existingKeys = array();
        $configData = array();
        foreach($lines as $line){
            preg_match('/define\s{0,}\(s{0,}(?P<key>.*)\s{0,},\s{0,}(?P<value>.*)\s{0,}\);/im', $line, $matches);
            if ($matches){
                $key = self::unquoteString(trim($matches['key'], ' '));
                $value = trim($matches['value'], ' ');
                foreach($existingKeys as $existingKey){
                    if (strpos($value, $existingKey) !== false){
                        $value = str_replace($existingKey, $configData[$existingKey], $value);
                    }
                }
                $configData[$key] = $value;
                $existingKeys[] = $key;
            }
        }

        foreach($configData as $confKey => $confValue){
            //$confValue = preg_replace('/(\'|")\s{0,}\.\s{0,}(\1)/', '', $confValue);
            $confValue = self::unquoteString($confValue);
            if ($confValue){
                $configData[$confKey] = $confValue;
            }
        }

        return $configData;
    }

    protected static function unquoteString($str)
    {
        $unquotedStr = $str;
        if (is_string($str) && preg_match("/^('|\")(?P<quoted>.*)(\\1)$/", $str, $matches)){
            $unquotedStr = $matches['quoted'];
        }

        return $unquotedStr;
    }

    public static function stripIncludes($filePath)
    {
        $content = file_get_contents($filePath);
        $content = preg_replace('/<\?(=|%|php)?/mi', '', $content);

        preg_match_all('~(require_once|require|include|include_once)[^\w_].*~mi', $content, $matches);

        $matches = $matches[0];
        foreach($matches as $match){
            $match = trim($match);
            $commentPos = strpos($match, '/*');
            if ($commentPos){
                $match = substr($match, 0, $commentPos);
            }
            $content = str_replace($match, '//' . $match . "\n", $content);
        }
        
        return $content;
    }

}
?><?php
class Bridge_Exception
{
    var $_warnings;

    var $throwExceptions;

    private function __construct()
    {

    }

    /**
     * @return Bridge_Exception
     */
    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Bridge_Exception();
        }

        return $instance;
    }

    /**
     * @param $message
     * @param $code
     */
    public static function ex($message, $code)
    {
        $obj = Bridge_Exception::getInstance();
        $xmlError = '
			<response>
				<error>
    				<type>Exception</type>
    				<backtrace><![CDATA[' . print_r($obj->_backtrace(), true) . ']]></backtrace>
    				<runtime><![CDATA[' . $obj->_runtimeInfo() . ']]></runtime>
    				<message><![CDATA[' . $message . ']]></message>
    				<code>' . $code . '</code>
    				<mysql_error><![CDATA[' . mysql_error() . ']]></mysql_error>
    				<hostname>' . $_SERVER['SERVER_NAME'] . '(' . $_SERVER['SERVER_ADDR'] . ')</hostname>
    				<ipaddr>' . $_SERVER['SERVER_ADDR'] . '</ipaddr>
    				<query>' . $_SERVER['REQUEST_URI'] . '</query>
				</error>
			</response>
		';

        header('X-msa-iserror: 1');
        die($xmlError);

    }

    /**
     * @param $message
     */
    function warn($message)
    {
        $this->_warnings[] = $message;
    }

    /**
     * @return string
     */
    function _runtimeInfo()
    {
        $info = 'PHP Version: ' . phpversion() . PHP_EOL
            . 'Webserver Version: ' . $_SERVER['SERVER_SOFTWARE'] . PHP_EOL;

        return $info;
        // 1. debug backtrace
        // 2. php version
        // 3. mysql version
        // 4. webserver version
        // 5. shopping cart type
        // 6. last mysql error
        // 7. last mysql query
    }

    /**
     * @return array|string
     */
    function _backtrace()
    {
        $trace = debug_backtrace();
        $m1_trace = array();
        $trace = array_reverse($trace, true);
        foreach ($trace as $i => $call) {
            if ($i == 0 || $i == 1) {
                continue;
            }

            $newIndex = $i - 2;

            $call['file'] = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath($call['file']));
            $call['file'] = str_replace('/' . basename($call['file']), '<b>' . '/' . basename($call['file']) . '</b>', $call['file']);

            $m1_trace .= "\n" .
                $newIndex . ': ' . $call['file'] . ':<b>' . $call['line'] . '</b> => '
                . $call['class'] . $call['type'] . '<b>' . $call['function'] . '</b>'
                . (!empty($call['args']) ? '("' . implode('","', $call['args']) . '")' : '()');
        }

        return $m1_trace;
    }
}

?><?php
class Bridge_Dispatcher
{
    function dispatch()
    {
        if (!isset($_REQUEST['module'])) {
            echo 'Bridge successfully installed';
            die;
        }

        $loader = Bridge_Loader::getInstance();
        if ($loader->getAccessKey() != $_REQUEST['accesskey']) {
            Bridge_Exception::ex('Hash is invalid', 'invalid_hash');
        }

        if (basename(__FILE__) == 'dispatcher.php') {
            define('ENVIRONMENT', 'development');
        }
        else {
            define('ENVIRONMENT', 'production');
        }

        $module = $_REQUEST['module'];
        $params = $_REQUEST['params'];

        if (isset($params['encoding']) && ($params['encoding'] === 'base64-serialize')) {
            $encodedParams = $params['value'];
            $params = unserialize(base64_decode($encodedParams));
        }

        $moduleClassName = 'Bridge_Module_' . ucfirst($module);

        $oModule = new $moduleClassName();
        /** @noinspection PhpUndefinedMethodInspection */
        $oModule->run($params);
    }

}

?><?php
/**
 * Database abstraction layer
 *
 */
class Bridge_Db
{
    /**
     * Default Mysql Connection link
     *
     * @var resource
     */
    var $_link;

    /**
     * Fetch into file chunk size
     *
     * @var int
     */
    var $_chunk_size = 300;

    /**
     * Profile state flag.
     *
     * @var bool
     */
    var $_enable_profiler = false;

    /**
     * Profiling result set
     *
     * @var array
     */
    var $_profiler_log = array();

    private function __construct()
    {

    }

    /**
     * Create instance of db connection layer
     *
     * @return Bridge_Db
     */
    public static function  getAdapter()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Bridge_Db();
        }

        return $instance;
    }

    /**
     * Enable or Disable profile
     *
     * @param bool $enable Pass true to enable profiler or false to disable it
     */
    function enableProfiler($enable = true)
    {
        $db = Bridge_Db::getAdapter();
        $db->_enable_profiler = ($enable ? true : false); // cast to boolean
    }

    /**
     * mysql_connect wrapper with error handling
     * Throws an Bridge_Exception if can't connect to database server
     *
     * @param string $host Host name or IP Address
     * @param string $username Database username
     * @param string $password Databse password in plain text format
     * @param string $dbname Database Name cannot be empty
     */
    function connect($host = 'localhost', $username = 'root', $password = '', $dbname = '')
    {
        if (!is_resource($this->_link)) {
            $this->_link = @mysql_connect($host, $username, $password);
            if (!$this->_link) {
                Bridge_Exception::ex('Cannot connect to MySql Server', 'db_error');
            }
            $this->setNames();
            $this->_setdb($dbname);
        }
    }

    function setNames()
    {
        if (!mysql_query('SET NAMES binary')) {
            Bridge_Exception::ex("Can't change database connection charset to binary", 'db_error');
        }
    }

    /**
     * mysql_select_db wrapper with error handling
     * Throws an Bridge_Exception if can't use database
     *
     * @param string $dbname
     */
    function _setdb($dbname)
    {
        if (!mysql_select_db($dbname, $this->_link)) {
            Bridge_Exception::ex("Can't find the database '" . $dbname . "'", 'db_error');
        }

    }

    /**
     * Returns default mysql link resource
     *
     * @return resource MySQL link resource
     */
    function getConnection()
    {
        return $this->_link;
    }

    /**
     * Fetch the all data for sql query and return an array
     *
     * @param string $sql SQL Plain SQL query
     * @param string $keyField primary key field
     * @return array Fetched rows array
     */
    function fetchAll($sql, $keyField = '')
    {
        $this->_profile_start();
        $rQuery = $this->execute($sql);
        $resultArr = array();
        while (($row = mysql_fetch_assoc($rQuery)) !== false) {
            if (isset($row[$keyField])) {
                $key = $row[$keyField];
                $resultArr[$key] = $row;
            }
            else {
                $resultArr[] = $row;
            }
        }
        mysql_free_result($rQuery);
        $this->_profile_end($sql);

        return $resultArr;
    }

    function fetchOne($sql)
    {
        $rQuery = $this->execute($sql);
        if (($row = mysql_fetch_assoc($rQuery)) !== false) {
            return current($row);
        }
        else {
            return false;
        }
    }

    /**
     * Fetch the all data for sql query and write it into gzipped file
     *
     * @param string $sql Plain SQL query
     * @param string $keyField Primary Key field
     * @internal param string $fileName Destination filename
     * @return string Result file full path
     */
    function fetchAllIntoFile($sql, $keyField = '')
    {

        $this->_profile_start();

        $rQuery = $this->execute($sql);
        $cFile = Bridge_Response::getFileHandler();
        $numRows = mysql_num_rows($rQuery);
        gzwrite($cFile, '<rows count="' . $numRows . '">');
        $buffer = '';
        $i = 0;
        while (($row = mysql_fetch_assoc($rQuery)) !== false) {

            $i++;
            $buffer .= '<row id="' . $row[$keyField] . '"><![CDATA[' . base64_encode(serialize($row)) . "]]></row>\n";
            if ($i % $this->_chunk_size == 0) {
                gzwrite($cFile, $buffer);
                $buffer = '';
                if ($i != $numRows) {
                    gzwrite($cFile, '</rows>');
                    // start new chunk
                    Bridge_Response::closeNode();
                    Bridge_Response::openNode();
                    $cFile = Bridge_Response::getFileHandler();
                    gzwrite($cFile, '<rows>');
                }
            }
        }
        if ($buffer != '') {
            gzwrite($cFile, $buffer);
        }
        gzwrite($cFile, '</rows>');

        $this->_profile_end($sql);

        return $rQuery;
    }

    /**
     * Store profiling start time
     *
     */
    function _profile_start()
    {
        if ($this->_enable_profiler) {
            /** @noinspection PhpUndefinedFieldInspection */
            $this->_startTime = time() + floatval(microtime(true));
        }
    }

    /**
     * Store profiling result time, sql and trace for debugging
     *
     * @param string $sql
     */
    function _profile_end($sql)
    {
        if ($this->_enable_profiler) {
            /** @noinspection PhpUndefinedFieldInspection */
            $this->_profiler_log[] = array(
                'sql' => $sql,
                'time' => (time() + floatval(microtime(true)) - $this->_startTime),
                'backtrace' => debug_backtrace()
            );
        }
    }

    /**
     * Execute passed SQL query using default mysql connetion
     *
     * @param string $sql Plain SQL query
     * @throws Exception
     * @return resource MySQL result set
     */
    function execute($sql)
    {
        $res = mysql_query($sql, $this->_link);
        // handle mysql error
        if ($res === false) {
            throw new Exception(mysql_error($this->_link));

        }

        return $res;
    }

    function affectedRows()
    {
        return mysql_affected_rows($this->_link);
    }

    function rowsCount($res)
    {
        return mysql_num_rows($res);
    }

    function lastInsertId()
    {
        return mysql_insert_id($this->_link);
    }

    function escape($string)
    {
        return mysql_real_escape_string($string, $this->_link);
    }

    public function getVariable($name)
    {
        $data = $this->fetchAll(sprintf("SHOW VARIABLES LIKE '%s'", $name));
        if (count($data) === 0) {
            Bridge_Exception::ex(sprintf('Variable %s is not found', $name), 'db_error');
        }
        $row = array_shift($data);

        return $row['Value'];
    }

    public function getMaxAllowedPacket()
    {
        $value = $this->getVariable('max_allowed_packet');

        return intval($value);
    }

    public static function getDbAdapter()
    {
        $db = Bridge_Db::getAdapter();
        if (!is_resource($db->_link)) {
            $config = Bridge_Loader::getInstance()->getCmsInstance()->getConfig();
            $db->connect(
                $config['db']['host'],
                $config['db']['user'],
                $config['db']['password'],
                $config['db']['dbname']
            );
        }

        return $db;
    }

    public function fetchDataChunkWithLimits($sql, $responseLimit)
    {
        $this->_profile_start();

        $rQuery = $this->execute($sql);
        $data = array();
        $responseSize = 0;
        while (($row = mysql_fetch_assoc($rQuery)) !== false) {
            $encodedRow = base64_encode(serialize($row));
            $rowSize = strlen($encodedRow);
            if ($responseSize > 0 && $responseSize + $rowSize > $responseLimit) {
                break;
            }
            $responseSize += $rowSize;
            $data[] = $encodedRow;
        }

        $this->_profile_end($sql);

        return $data;
    }

}

?><?php
class Bridge_Base
{
    function getShoppingCartType()
    {
        return Bridge_Loader::getInstance()->detectCms();
    }

    function _matchFirst($pattern, $subject, $matchNum = 0)
    {
        $matches = array();
        preg_match($pattern, $subject, $matches);

        return $matches[$matchNum];
    }

}

?><?php
class Bridge_Module_Info
{

    function run()
    {
        $loader = Bridge_Loader::getInstance();
        $bridgeVersion = $loader->getBridgeVersion();

        $currentCms = $loader->getCmsInstance();

        $config = $currentCms->getConfig();
        $imgDirectory = $currentCms->getImageDir();
        $modules = $currentCms->detectExtensions();

        $imgDirectory = $loader->getFs()->getLocalRelativePath($imgDirectory);
        $imgAbsPath = $loader->getCurrentPath() . $imgDirectory;
        $isWritable = is_writable($imgAbsPath);

        $params = array(
            'phpVersion' => phpversion(),
            'db_prefix' => $config['db']['dbprefix'],
            'db_driver' => $config['db']['driver'],
            'max_allowed_packet' => Bridge_Db::getDbAdapter()->getMaxAllowedPacket(),
            'max_request_size' => ini_get('post_max_size'),
            'charset' => array(
                'web_server' => $this->getWebServerCharset(),
                'php' => $this->getPhpCharset(),
                'mysql' => $this->getMySqlCharset(),
                'db' => '' //$this->getDbCharset($config['db']['dbname'])
            ),
            'imageDirectoryOption' => array(
                'path' => $imgDirectory,
                'isWritable' => $isWritable
            ),
            'seoParams' => isset($config['seo']) ? $config['seo'] : array()
        );

        $infoNew = array(
            'bridge_version' => $bridgeVersion,
            'cart_type' =>  $config['CMSType'],
            'cart_version' => $config['version'],
            'params' => $params,
            'modules' => $modules
        );
        $encodedInfo = base64_encode(serialize($infoNew));

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('info');
        $response->sendNode('bridge_version', $bridgeVersion);
        $response->sendNode('cart_type', $config['CMSType']);
        $response->sendNode('cart_version', $config['version']);
        //$response->sendNode('params', serialize($params));
        //$response->sendNode('modules', serialize($modules));
        $response->sendNode('encodedResult', $encodedInfo);
        $response->closeNode();
    }

    protected function getWebServerCharset()
    {
        return isset($_SERVER['HTTP_ACCEPT_CHARSET'])?$_SERVER['HTTP_ACCEPT_CHARSET']:'';
    }

    protected function getPhpCharset()
    {
        return ini_get('default_charset');
    }

    protected function fetchOneStrFromDb($sql)
    {
        $str = "";
        $db = Bridge_Db::getDbAdapter();
        $res = $db->fetchOne($sql);
        if ($res !== false && is_string($res) && strlen($res) > 0) {
            $str = $res;
        }

        return $str;
    }

    protected function getDbCharset($dbName)
    {
        $sql = sprintf(
            "
                SELECT `s`.`default_character_set_name`
                FROM `information_schema`.`SCHEMATA` `s`
                WHERE schema_name = '%s'
            ",
            $dbName
        );

        return $this->fetchOneStrFromDb($sql);
    }

    protected function getMySqlCharset()
    {
        $sql = "SELECT @@character_set_server";

        return $this->fetchOneStrFromDb($sql);
    }

}

?><?php
class Bridge_Module_Dbsql2
{

    protected $db;

    /**@var Bridge_Response_Memory */
    protected $response;

    public function __construct()
    {
        $this->db = Bridge_Db::getDbAdapter();
        /**@var $response Bridge_Response_Memory */
        $this->response = Bridge_Response::getInstance('Bridge_Response_Memory');
    }

    protected function getDefaultRowsLimit()
    {
        return 100;
    }

    protected function getDefaultResponseLimit()
    {
        return 4 * 1024 * 1024;
    }

    protected function getSqlType($sqlQuery)
    {
        $sqlQuery = strtolower(trim($sqlQuery));
        if (strpos($sqlQuery, 'select') === 0
            || strpos($sqlQuery, 'show') === 0
            || strpos($sqlQuery, 'describe') === 0
        ) {
            return 'fetch';
        }

        return 'exec';
    }

    protected function fetchData($sql, $responseLimit)
    {
        $rowsData = $this->db->fetchDataChunkWithLimits($sql, $responseLimit);

        $this->response->openNode('rows');
        foreach ($rowsData as $itemData) {
            $this->response->sendNode('row', $itemData);
        }
        $this->response->closeNode();

        header('X-msa-db-rowscount: ' . count($rowsData));
    }

    protected function exec($sql)
    {
        $this->db->execute($sql);
        header('X-msa-emptyqueryresult: 1');
        header('X-msa-db-affectedrows: ' . $this->db->affectedRows());
        header('X-msa-db-lastinsertid: ' . $this->db->lastInsertId());
    }

    function run($params)
    {
        $sql = base64_decode($params['sql']);
        $sqlType = $this->getSqlType($sql);
        switch ($sqlType) {
            case 'fetch' :
                $responseLimit = $params['responseLimit'];
                $this->fetchData($sql, $responseLimit);
                break;
            case 'exec' :
                $this->exec($sql);
                break;
            default:
                Bridge_Exception::ex(sprintf('Unknown sql type %s', $sqlType), null);
        }
    }
}

?><?php
class Bridge_Module_Transfer
{

    protected function getCleanHost($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host);

        return $host;
    }

    protected function getTransferMode($sourceUrl, $targetUrl)
    {
        $cleanSource = $this->getCleanHost($sourceUrl);
        $cleanTarget = $this->getCleanHost($targetUrl);

        $mode = 'remote';
        if ($cleanSource === $cleanTarget){
            return 'local';
        }

        return $mode;
    }

    // http://ua2.php.net/manual/en/function.parse-url.php
    function combineUrl($parsedUrl) {
        $scheme   = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host     = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $port     = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $user     = isset($parsedUrl['user']) ? $parsedUrl['user'] : '';
        $pass     = isset($parsedUrl['pass']) ? ':' . $parsedUrl['pass']  : '';
        $pass     = ($user || $pass) ? $pass . "@" : '';
        $path     = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $query    = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }

    protected function encodeUrl($url)
    {
        if (rawurldecode($url) !== $url){
            return $url;
        }

        $urlParts = parse_url($url);
        if (isset($urlParts['path'])){
            $encodedPathParts = array();
            $pathParts = explode('/', $urlParts['path']);
            foreach($pathParts as $pathPart){
                $encodedPathParts[] = rawurlencode($pathPart);
            }

            $urlParts['path'] = implode('/', $encodedPathParts);
        }

        $url = $this->combineUrl($urlParts);

        return $url;
    }

    protected function parseRedirectUrlFromCurlResponse($data)
    {
        list($header) = explode("\r\n\r\n", $data, 2);

        $matches = array();
        preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
        $url = trim(str_replace($matches[1], "", $matches[0]));

        $url_parsed = parse_url($url);
        if (!isset($url_parsed)) {
            throw new Exception(sprintf('Bad redirect url %s', $url));
        }

        return $url;
    }

    protected function curlRedirectSafeMode($ch, $redirects)
    {
        if ($redirects < 0){
            throw new Exception('Too many redirects');
        }

        $data = curl_exec($ch);

        $errNo = curl_errno($ch);
        if ($errNo) {
            throw new Exception(sprintf('cURL error %s', $errNo));
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $code = intval($code);
        if ($code === 200){
            return $data;
        }

        if ($code == 301 || $code == 302) {
            $url = $this->parseRedirectUrlFromCurlResponse($data);
            curl_setopt($ch, CURLOPT_URL, $url);

            return $this->curlRedirectSafeMode($ch, $redirects - 1);
        }

        throw new Exception(sprintf('Response code is %s', $code));
    }

    //http://www.php.net/manual/en/function.curl-setopt.php#95027
    protected function curlExec($ch, $redirects)
    {
        if ((ini_get('open_basedir') == '') && (ini_get('safe_mode') == 'Off')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $redirects);
            $data = curl_exec($ch);
        }
        else {
            $data = $this->curlRedirectSafeMode($ch, $redirects);
        }

        list(, $body) = explode("\r\n\r\n", $data, 2);

        return $body;
    }

    protected function transferRemoteFile($sourceUrl, $targetPath)
    {
        $host = parse_url($sourceUrl, PHP_URL_HOST);
        $encodedUrl = $this->encodeUrl($sourceUrl);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $encodedUrl);
        curl_setopt($ch, CURLOPT_REFERER, $host);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CMS2CMS Bridge');

        $body = $this->curlExec($ch, 5);

        $fp = fopen($targetPath, 'w');
        if (!$fp){
            throw new Exception(sprintf('Can not write into %s', $targetPath));
        }

        fwrite($fp, $body);
        fclose($fp);

        return $targetPath;
    }

    protected function transferLocalFile($sourcePath, $targetPath)
    {
        if (!file_exists($sourcePath)){
            throw new Exception(sprintf('%s does not exists', $sourcePath));
        }

        if (!is_readable($sourcePath)){
            throw new Exception(sprintf('%s is not readable', $sourcePath));
        }

        if ($sourcePath === $targetPath){
            return $targetPath;
        }

        if (!copy($sourcePath, $targetPath)){
            throw new Exception(sprintf('Copy %s to %s failed', $sourcePath, $targetPath));
        }

        return $targetPath;
    }

    protected function transferFile($sourceHost, $targetHost, $sourceUrl, $targetUrl)
    {
        $success = array();
        $error = array();

        $transferResult = null;
        $targetCopies = array();
        if (is_array($targetUrl)){
            $targetCopies = array_splice($targetUrl, 1);
            $targetUrl = array_pop($targetUrl);
        }

        $loader = Bridge_Loader::getInstance();

        $method = $this->getTransferMode($sourceHost, $targetHost);

        $sourcePath = $loader->getFs()->getLocalAbsPath($sourceHost, $sourceUrl);

        $targetPath = $loader->getFs()->getLocalAbsPath($targetHost, $targetUrl);
        $loader->getFs()->createPathIfNotExists($targetPath);

        if (is_dir($targetPath)){
            throw new Exception(sprintf('%s is a directory', $targetPath));
        }

        $targetDir = dirname($targetPath);
        if (!is_writable($targetDir)){
            throw new Exception(sprintf('Directory %s is not writable', $targetDir));
        }


        if ($method === 'local'){
            try {
                $success[] = $this->transferLocalFile($sourcePath, $targetPath);
            }
            catch(Exception $e){
                $method = 'remote';
            }
        }

        if ($method === 'remote'){
            $success[] = $this->transferRemoteFile($sourceUrl, $targetPath);
        }

        foreach($targetCopies as $targetUrl){
            $targetCopy = $loader->getFs()->getLocalAbsPath($targetHost, $targetUrl);
            try {
                $success[] = $this->transferLocalFile($targetPath, $targetCopy);
            }
            catch(Exception $e){
                $error[] = $e->getMessage();
            }
        }

        $transferResult = array(
            'success' => $success,
            'error' => $error
        );

        return $transferResult;
    }

    protected function transferFileList($sourceHost, $targetHost, array $transferList)
    {
        $transferResults = array();

        foreach($transferList as $source => $target){
            try {
                $transferResults[$source] = $this->transferFile($sourceHost, $targetHost, $source, $target);
            }
            catch(Exception $e){
                $transferResults[$source] = array('error' => array($e->getMessage()));
            }
        }

        return $transferResults;
    }

    public function transferFiles(array $params)
    {
        if (!isset($params['sourceHost'])){
            throw new Exception('Source is required');
        }

        if (!isset($params['targetHost'])){
            throw new Exception('Target is required');
        }

        if (!isset($params['list'])){
            throw new Exception('Transfer list is required');
        }

        $sourceHost = $params['sourceHost'];
        $targetHost = $params['targetHost'];
        $transferList = $params['list'];

        if (!is_array($transferList)){
            throw new Exception('Bad transfer list format');
        }

        if (count($transferList) === 0){
            throw new Exception('Transfer list is empty');
        }

        return $this->transferFileList($sourceHost, $targetHost, $transferList);
    }

    public function run($params = array())
    {
        try {
            $results = $this->transferFiles($params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), null);
            return;
        }

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('transfer');
        $response->sendNode('results', serialize($results));
        $response->closeNode();
    }

}
?><?php
class Bridge_Module_Resize
{

    /**
     * Calculates image crop by width and height
     *
     * @param $imageWidth
     * @param $imageHeight
     * @param $resizeWidth
     * @param $resizeHeight
     * @return array $imageSize array with height and width keys
     */
    protected function getCropSize($imageWidth, $imageHeight, $resizeWidth, $resizeHeight)
    {
        $marginLeft = 0;
        $marginTop = 0;

        $paddingLeft = 0;
        $paddingTop = 0;

        if ($resizeWidth > $imageWidth){
            $diffWidth = $resizeWidth - $imageWidth;
            $marginLeft = intval(floor($diffWidth / 2));

            $innerWidth = $imageWidth;
            $outerWidth = $imageWidth;
        }
        else {
            $kWidth = ($imageWidth * $resizeHeight) / ($imageHeight * $resizeWidth);

            $newWidth = intval(floor($imageWidth / $kWidth));
            $newWidth = min($newWidth, $imageWidth);

            $widthDiff = $imageWidth - $newWidth;
            $paddingLeft = intval(floor(($widthDiff) / 2));

            $innerWidth = $imageWidth - ($paddingLeft * 2);
            $outerWidth = $resizeWidth;
        }

        if ($resizeHeight > $imageHeight){
            $diffHeight = $resizeHeight - $imageHeight;
            $marginTop = intval(floor($diffHeight / 2));

            $innerHeight = $imageHeight;
            $outerHeight = $imageHeight;
        }
        else {
            $kHeight = ($imageHeight * $resizeWidth) / ($imageWidth * $resizeHeight);

            $newHeight = intval(floor($imageHeight / $kHeight));
            $newHeight = min($newHeight, $imageHeight);

            $diffHeight = $imageHeight - $newHeight;
            $paddingTop = intval(floor(($diffHeight) / 2));

            $innerHeight = $imageHeight - ($paddingTop * 2);
            $outerHeight = $resizeHeight;
        }

        $cropSize = array(
            'marginLeft' => $marginLeft,
            'marginTop' => $marginTop,

            'paddingLeft' => $paddingLeft,
            'paddingTop' => $paddingTop,

            'innerWidth' => $innerWidth,
            'innerHeight' => $innerHeight,

            'outerWidth' => $outerWidth,
            'outerHeight' => $outerHeight
        );

        return $cropSize;
    }

    protected function getImageTypeByExtension($filePath)
    {
        $type = strtolower(substr(strrchr($filePath, '.'), 1));
        if($type == 'jpeg') {
            $type = 'jpg';
        }

        return $type;
    }

    protected function getImageFromFile($filePath)
    {
        $data = file_get_contents($filePath);
        $image = imagecreatefromstring($data);

        return $image;
    }

    protected function saveImageToFile($image, $type, $filePath)
    {
        ob_start();
        switch($type){
            case 'bmp':
                $saveSuccess = imagewbmp($image);
                break;
            case 'gif':
                $saveSuccess = imagegif($image);
                break;
            case 'jpg':
                $saveSuccess = imagejpeg($image);
                break;
            case 'png':
                $saveSuccess = imagepng($image);
                break;
            default:
                throw new Exception('Unsupported picture type');
        }

        $data = ob_get_clean();

        if (!$saveSuccess){
            throw new Exception('Save failed');
        }

        file_put_contents($filePath, $data);
    }

    protected function doResize($image, $resizeHeight, $resizeWidth)
    {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        if ($resizeWidth === -1 && $resizeHeight === -1){
            throw new Exception(sprintf('Can not resize %s %s', $resizeWidth, $resizeHeight));
        }

        if ($resizeWidth === -1 && $resizeHeight > 0){
            $resizeWidth = ($imageWidth * $resizeHeight) / $imageHeight;
            $resizeWidth = intval(floor($resizeWidth));
        }

        if ($resizeHeight === -1 && $resizeWidth > 0){
            $resizeHeight = ($imageHeight * $resizeWidth) / $imageWidth;
            $resizeHeight = intval(floor($resizeHeight));
        }

        $cropSize = $this->getCropSize($imageWidth, $imageHeight, $resizeWidth, $resizeHeight);
        $resultImage = imagecreatetruecolor($resizeWidth, $resizeHeight);

        $color = imagecolorallocate($resultImage, 255, 255, 255);
        imagefilledrectangle($resultImage, 0, 0, $resizeWidth, $resizeHeight, $color);

        $copySuccess = imagecopyresampled(
            $resultImage, $image,

            $cropSize['marginLeft'],
            $cropSize['marginTop'],

            $cropSize['paddingLeft'],
            $cropSize['paddingTop'],

            $cropSize['outerWidth'],
            $cropSize['outerHeight'],

            $cropSize['innerWidth'],
            $cropSize['innerHeight']
        );

        if (!$copySuccess){
            throw new Exception('Resize failed');
        }

        return $resultImage;
    }

    public function resize($sourcePath, $targetPath, $resizeWidth, $resizeHeight)
    {
        if (!file_exists($sourcePath)){
            throw new Exception(sprintf('File %s does not exists', $sourcePath));
        }

        $type = $this->getImageTypeByExtension($sourcePath);

        $image = $this->getImageFromFile($sourcePath);
        $resultImage = $this->doResize($image, $resizeHeight, $resizeWidth);
        $this->saveImageToFile($resultImage, $type, $targetPath);

        imagedestroy($image);
        imagedestroy($resultImage);

        return array(
            'targetPath' => $targetPath
            /*,
            'original' => array(
                'width' => $imageWidth,
                'height' => $imageHeight
            )
            */
        );
    }

    protected function resizeImage($resizeHost, $resizeParams)
    {
        if (!isset($resizeParams['sourcePath'])){
            throw new Exception('SourcePath is required');
        }

        if (!isset($resizeParams['targetPath'])){
            throw new Exception('TargetPath is required');
        }

        if (!isset($resizeParams['width'])){
            throw new Exception('Width is required');
        }

        if (!isset($resizeParams['height'])){
            throw new Exception('Height is required');
        }

        $source = $resizeParams['sourcePath'];
        $target = $resizeParams['targetPath'];

        $width = $resizeParams['width'];
        $width = intval($width);

        $height = $resizeParams['height'];
        $height = intval($height);

        $loader = Bridge_Loader::getInstance();
        $sourcePath = $loader->getFs()->getLocalAbsPath($resizeHost, $source);

        $targetPath = $loader->getFs()->getLocalAbsPath($resizeHost, $target);
        $loader->getFs()->createPathIfNotExists($targetPath);

        return $this->resize($sourcePath, $targetPath, $width, $height);
    }

    protected function resizeImages($params)
    {
        if (!isset($params['resizeHost'])){
            throw new Exception('Resize host is required');
        }

        if (!isset($params['list'])){
            throw new Exception('Resize list is required');
        }

        $resizeHost = $params['resizeHost'];

        $resizeList = $params['list'];
        if (!is_array($resizeList)){
            throw new Exception('Resize list must be array');
        }

        $success = array();
        $error = array();

        foreach($resizeList as $index => $resizeParams){
            try {
                $success[$index] = $this->resizeImage($resizeHost, $resizeParams);
            }
            catch(Exception $e){
                $error[$index] = $e->getMessage();
            }
        }

        $results = array(
            'success' => $success,
            'error' => $error
        );

        return $results;
    }

    public function run($params = array())
    {
        try {
            $results = $this->resizeImages($params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'image_resize');
            return;
        }

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('resize');
        $response->sendNode('results', serialize($results));
        $response->closeNode();
    }

}
?><?php
class Bridge_Module_ImageSize
{

    public function getSize($host, $sourceUrl)
    {
        $loader = Bridge_Loader::getInstance();
        $sourcePath = $loader->getFs()->getLocalAbsPath($host, $sourceUrl);

        if (!file_exists($sourcePath)){
            throw new Exception(sprintf('File %s does not exists', $sourcePath));
        }

        $data = file_get_contents($sourcePath);
        $image = imagecreatefromstring($data);

        if ($image === false){
            throw new Exception(sprintf('Can not read image %s', $sourcePath));
        }

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        imagedestroy($image);

        return array(
            'width' => $imageWidth,
            'height' => $imageHeight
        );
    }

    protected function getSizes($params)
    {
        if (!isset($params['sizeHost'])){
            throw new Exception('Size host is required');
        }

        if (!isset($params['list'])){
            throw new Exception('Resize list is required');
        }

        $host = $params['sizeHost'];

        $list = $params['list'];
        if (!is_array($list)){
            throw new Exception('Resize list must be array');
        }

        $success = array();
        $error = array();

        foreach($list as $index => $sourceUrl){
            try {
                $success[$index] = $this->getSize($host, $sourceUrl);
            }
            catch(Exception $e){
                $error[$index] = $e->getMessage();
            }
        }

        $results = array(
            'success' => $success,
            'error' => $error
        );

        return $results;
    }

    public function run($params = array())
    {
        try {
            $results = $this->getSizes($params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'image_size');
            return;
        }

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('size');
        $response->sendNode('results', serialize($results));
        $response->closeNode();
    }

}
?><?php
class Bridge_Module_Fs
{

    protected $db;

    /**@var $response Bridge_Response_Memory */
    protected $response;

    public function __construct()
    {
        $this->db = Bridge_Db::getDbAdapter();
        /**@var $response Bridge_Response_Memory */
        $this->response = Bridge_Response::getInstance('Bridge_Response_Memory');
    }

    public function runFileExists(array $params)
    {
        if (!isset($params['list'])){
            throw new Exception('List params is missing');
        }

        $list = $params['list'];
        if (!is_array($list)){
            throw new Exception('Bad list type');
        }

        $loader = Bridge_Loader::getInstance();
        $rootDir = $loader->getCurrentPath();

        $existingFiles = array();
        foreach($list as $relativePath){
            $absPath = $rootDir . $relativePath;
            if (file_exists($absPath)){
                $existingFiles[] = $relativePath;
            }
        }

        $this->response->sendNode('fileExists', serialize($existingFiles));
    }

    public function doOperation($operation, $params)
    {
        switch($operation){
            case 'file-exists':
                $this->runFileExists($params);
                break;
            default:
                throw new Exception(sprintf('Unknown fs operation %s', $operation), null);
        }
    }

    public function run($params)
    {
        if (!isset($params['operation'])){
            Bridge_Exception::ex('Type param is missing', 'dump_error');
        }

        $operation = $params['operation'];

        $this->response->openNode('fs');
        try {
            $this->doOperation($operation, $params);
        }
        catch(Exception $e){
            Bridge_Exception::ex($e->getMessage(), 'fs_error');
        }
        $this->response->closeNode();
    }

}
?><?php
class Bridge_Module_Dump
{

    protected $db;

    /**@var $response Bridge_Response_Memory */
    protected $response;

    public function __construct()
    {
        $this->db = Bridge_Db::getDbAdapter();
        /**@var $response Bridge_Response_Memory */
        $this->response = Bridge_Response::getInstance('Bridge_Response_Memory');
    }

    protected function runListTables()
    {
        $sql = 'SHOW TABLES';
        $tables = $this->db->fetchAll($sql);

        $this->response->openNode('tables');
        foreach ($tables as $table) {
            $tableName = array_pop($table);
            $this->response->sendNode('table', $tableName);
        }
        $this->response->closeNode();
    }

    protected function runShowCreate(array $params)
    {
        if (!isset($params['table'])) {
            throw new Exception('Table param is missing');
        }

        $table = $params['table'];

        $sql = sprintf(
            'SHOW CREATE TABLE `%s`',
            $table
        );

        $rows = $this->db->fetchAll($sql);
        if (count($rows) === 0) {
            throw new Exception();
        }

        $firstRow = array_pop($rows);
        $statement = array_pop($firstRow);

        $this->response->sendNode('statement', $this->encode($statement));
    }

    protected function runExecCreate($params)
    {
        if (!isset($params['createStatement'])) {
            throw new Exception('Statement param is missing');
        }

        if (!isset($params['dropStatement'])) {
            throw new Exception('Table param is missing');
        }

        $dropSql = base64_decode($this->decode($params['dropStatement']));
        $createSql = base64_decode($this->decode($params['createStatement']));

        try {
            $this->db->execute($createSql);
            $this->db->execute($dropSql);
            $this->db->execute($createSql);
        }
        catch (Exception $e) {
            Bridge_Exception::ex($e->getMessage(), 'db_error');
        }
    }

    protected function getDumpQuery($table, $limit, $offset, $filter = '')
    {
        $sql = sprintf(
            'SELECT * FROM `%s`',
            $table
        );

        if ($filter !== '') {
            $sql .= ' ' . $filter;
        }

        $sql .= ' ' . sprintf('LIMIT %s', $limit);
        $sql .= ' ' . sprintf('OFFSET %s', $offset);

        return $sql;
    }

    protected function runSelect(array $params)
    {
        if (!isset($params['table'])) {
            throw new Exception('Table param is missing');
        }

        if (!isset($params['limit'])) {
            throw new Exception('Limit param is missing');
        }

        if (!isset($params['offset'])) {
            throw new Exception('Offset param is missing');
        }

        $table = $params['table'];
        $limit = $params['limit'];
        $offset = $params['offset'];

        $filter = '';
        if (isset($params['filter'])) {
            $filter = $params['filter'];
        }

        $responseLimit = $params['responseLimit'];

        $sql = $this->getDumpQuery($table, $limit, $offset, $filter);
        $rowsData = $this->db->fetchDataChunkWithLimits($sql, $responseLimit);

        $this->response->openNode('rows');
        foreach ($rowsData as $itemData) {
            $this->response->sendNode('row', $itemData);
        }
        $this->response->closeNode();

        header('X-msa-db-rowscount: ' . count($rowsData));
    }

    protected function getInsertQuery($table, $data)
    {
        $fieldNamesEscaped = array();
        $fieldValuesEscaped = array();
        $dublicatedFields = array();
        $dublicatedString = '';
        foreach ($data as $fieldName => $fieldValue) {
            $fieldNamesEscaped[] = '`' . $fieldName . '`';
            if ($fieldValue !== null) {
                $fieldValuesEscaped[] = '"' . ($this->db->escape($fieldValue)) . '"';
            }
            else {
                $fieldValuesEscaped[] = 'null';
            }

            $dublicatedFields[] = sprintf('%s = values(%s)', '`' . $fieldName . '`', '`' . $fieldName . '`');
        }

        if (count($dublicatedFields) > 0) {
            $dublicatedString = ' ON DUPLICATE KEY UPDATE ' . implode(', ', $dublicatedFields);
        }

        $fieldsStr = implode(',', $fieldNamesEscaped);
        $valuesStr = implode(',', $fieldValuesEscaped);

        $sql = sprintf(
            'INSERT INTO `%s`(%s) VALUES (%s) %s',
            $table,
            $fieldsStr,
            $valuesStr,
            $dublicatedString
        );

        return $sql;
    }

    protected function runInsert(array $params)
    {
        if (!isset($params['table'])) {
            throw new Exception('Table param is missing');
        }

        if (!isset($params['rows'])) {
            throw new Exception('Rows param is missing');
        }

        $table = $params['table'];
        $rows = $params['rows'];

        $errors = array();
        foreach ($rows as $index => $row) {
            $data = unserialize(base64_decode($this->decode($row)));
            $sql = $this->getInsertQuery($table, $data);

            try {
                $this->db->execute($sql);
            }
            catch (Exception $e) {
                $errors[$index] = $e->getMessage();
            }
        }

        $this->response->openNode('errors');
        foreach ($errors as $rowIndex => $message) {
            $this->response->openNode('error');
            $this->response->sendNode('row', $rowIndex);
            $this->response->sendNode('message', $message);
            $this->response->closeNode();
        }
        $this->response->closeNode();
    }

    protected function runExecute(array $params)
    {
        if (!isset($params['queryData'])) {
            throw new Exception('QueryData param is missing');
        }

        $sql = $params['queryData'];
        $result = $this->db->execute($sql);

        $this->response->sendNode('result', is_bool($result));
    }

    protected function runCount(array $params)
    {
        if (!isset($params['table'])) {
            throw new Exception('Table param is missing');
        }

        $table = $params['table'];

        $sql = sprintf(
            "SELECT COUNT(*) AS `count` FROM `%s`",
            $table
        );

        $count = $this->db->fetchOne($sql);

        $this->response->sendNode('count', $count);
    }

    protected function runDelete(array $params)
    {
        if (!isset($params['table'])) {
            throw new Exception('Table param is missing');
        }

        if (!isset($params['count'])) {
            throw new Exception('Table param is missing');
        }

        $table = $params['table'];
        $count = $params['count'];

        $count = intval($count);

        $sql = sprintf(
            "DELETE FROM `%s` WHERE 1=1 LIMIT %s",
            $table,
            $count
        );

        $this->db->execute($sql);
        $count = $this->db->affectedRows();

        $this->response->sendNode('deleteCount', $count);
    }

    protected function doOperation($operation, $params)
    {
        switch ($operation) {
            case 'list-tables':
                $this->runListTables();
                break;
            case 'exec-create':
                $this->runExecCreate($params);
                break;
            case 'show-create':
                $this->runShowCreate($params);
                break;
            case 'select':
                $this->runSelect($params);
                break;
            case 'insert':
                $this->runInsert($params);
                break;
            case 'execute':
                $this->runExecute($params);
                break;
            case 'count':
                $this->runCount($params);
                break;
            case 'delete':
                $this->runDelete($params);
                break;
            default:
                Bridge_Exception::ex(sprintf('Unknown dump type %s', $operation), null);
        }
    }

    function run($params)
    {
        if (!isset($params['operation'])) {
            Bridge_Exception::ex('Type param is missing', 'dump_error');
        }
        $operation = $params['operation'];

        $this->response->openNode('dump');
        try {
            $this->db->setNames();
            $this->doOperation($operation, $params);
        }
        catch (Exception $e) {
            Bridge_Exception::ex($e->getMessage(), 'dump_error');
        }
        $this->response->closeNode();
    }

    public  function decode($value)
    {
        return base64_decode($value);
    }

    public  function encode($value)
    {
        return base64_encode($value);
    }
}

?><?php
class Bridge_Module_FileList
{
    /**
     * @param array  $directory
     */
    function run(array $directory)
    {
        $currentCms = Bridge_Loader::getInstance()->getCmsInstance();
        $fileList = $currentCms->getFileList($directory);

        $encodedFileList = base64_encode(serialize($fileList));

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->openNode('fileList');
        $response->sendNode('ImageEncoded',  $encodedFileList);
        $response->closeNode();
    }
}

?><?php
class Bridge_Module_Install
{
    const PLUGIN_DIRECTORY_PATH = "/wp-content/plugins/";
    const DOWNLOADED_ZIPNAME = "TmpFile.zip";

    var $plugin = '';

    function Bridge_Module_Install()
    {
        $this->plugin = ucfirst(htmlentities($_REQUEST["params"]["plugin"]));
    }

    function getModuleClass()
    {
        $moduleClassName = 'Bridge_Module_Install_' . $this->plugin;

        $plugin = new $moduleClassName();

        return $plugin;
    }

    function run()
    {
        $plugin = $this->getModuleClass();
        $config = $plugin->getPluginConfig();

        if (!$plugin->copyFiles($config)) {
            Bridge_Exception::ex('We can not copy plugin files', 'install error');
        }

        if (!$this->unzip()) {
            Bridge_Exception::ex('We can not extract plugin files', 'install error');
        }

        if (!$this->checkIsUnZipped($config)) {
            Bridge_Exception::ex('Plugin not found', 'install error');
        }

        $result = unserialize($this->getWordPressPluginList());
        $this->checkPluginIsAlreadyInstall($result, $config);
        $result[] = $config["pluginName"];

        $plugin->activatePlugin($result);

        /**@var $response Bridge_Response_Memory */
        $response = Bridge_Response::getInstance('Bridge_Response_Memory');
        $response->sendNode("results", "Plugin install");
    }

    function copyFiles($config)
    {
        $result = false;
        try {
            $path = $this->getPath() . self::PLUGIN_DIRECTORY_PATH;
            $result = file_put_contents($path . self::DOWNLOADED_ZIPNAME, fopen($config["downloadLink"], 'r'));
        } catch (Exception $e) {
            Bridge_Exception::ex('We can not copy plugin files', 'install error');
        }

        return $result;
    }

    function unzip()
    {
        if (!extension_loaded('zip')) {
            Bridge_Exception::ex('Zip archive is not loaded in php', 'install error');
        }

        $path = $this->getPath() . self::PLUGIN_DIRECTORY_PATH . self::DOWNLOADED_ZIPNAME;
        $zip = new ZipArchive;
        $res = $zip->open($path);

        if ($res === TRUE) {
            $zip->extractTo($this->getPath() . self::PLUGIN_DIRECTORY_PATH);
            $zip->close();
        } else {
            Bridge_Exception::ex('Can not find plugin to extract', 'install error');
        }

        unlink($this->getPath() . self::PLUGIN_DIRECTORY_PATH . self::DOWNLOADED_ZIPNAME);

        return true;
    }

    function checkIsUnZipped($config)
    {
        $listOfPlugins = scandir($this->getPath() . self::PLUGIN_DIRECTORY_PATH);
        $isPluginUnZipped = in_array($config["pluginDirName"], $listOfPlugins);

        return $isPluginUnZipped;
    }

    function getWordPressPluginList()
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            '
                SELECT `option_value`
                FROM `%s` WHERE `option_name` = "active_plugins"
            ',
            $this->prefixTable('options')
        );

        return $db->fetchOne($sql, 'name');
    }

    function checkPluginIsAlreadyInstall($modules, $config)
    {
        if (in_array($config["pluginName"], $modules)) {
            Bridge_Exception::ex('Plugin is already installed', 'install error');
        }
    }

    function activatePlugin($value)
    {
        $db = Bridge_Db::getDbAdapter();
        $value = serialize($value);

        $sql = sprintf(
            "
                UPDATE `%s`
                SET `option_value` = '%s'
                WHERE `option_name` = 'active_plugins'
            ",
            $this->prefixTable('options'), $value
        );

        return $db->execute($sql);
    }

    function getConfig()
    {
        return $config = Bridge_Loader::getInstance()->getCmsInstance()->getConfig();
    }

    function getPath()
    {
        return Bridge_Loader::getInstance()->getCurrentPath();
    }

    protected function getTablePrefix($tableName)
    {
        $config = $this->getConfig();
        $prefix = '';
        if (isset($config['db']['dbprefix'])) {
            $prefix = $config['db']['dbprefix'];
        }

        if (is_array($prefix)) {
            if (isset($prefix[$tableName])) {
                $prefix = $prefix[$tableName];
            } else {
                $prefix = '';
            }
        }

        return $prefix;
    }

    protected function prefixTable($tableName)
    {
        $prefix = $this->getTablePrefix($tableName);

        return $prefix . $tableName;
    }
}
?><?php

class Bridge_Module_Install_BbPress extends Bridge_Module_Install
{
    function getPluginConfig()
    {
        return array(
            "downloadLink" => "https://downloads.wordpress.org/plugin/bbpress.2.5.4.zip",
            "pluginName" => "bbpress/bbpress.php",
            "pluginDirName" => "bbpress",
        );
    }
}
?><?php
abstract class Bridge_Module_Cms_Abstract
{
    protected $config;

    protected function getTablePrefix($tableName)
    {
        $config = $this->getConfig();
        $prefix = '';
        if (isset($config['db']['dbprefix'])) {
            $prefix = $config['db']['dbprefix'];
        }

        if (is_array($prefix)) {
            if (isset($prefix[$tableName])) {
                $prefix = $prefix[$tableName];
            }
            else {
                $prefix = '';
            }
        }

        return $prefix;
    }

    protected function prefixTable($tableName)
    {
        $prefix = $this->getTablePrefix($tableName);

        return $prefix . $tableName;
    }

    public function getConfig()
    {
        if ($this->config == null) {
            $this->config = $this->getConfigFromConfigFiles();
        }

        return $this->config;
    }

    public function getFileList(array $params)
    {
        $directory = DIRECTORY_SEPARATOR;
        if (isset($params['directory']) && is_array($params['directory'])) {
            $directory .= implode(DIRECTORY_SEPARATOR, $params['directory']);
        }
        if (is_dir(Bridge_Loader::getInstance()->getCurrentPath() . $directory)) {
            $fileList = array(
                $directory => scandir(Bridge_Loader::getInstance()->getCurrentPath() . $directory)
            );
        }
        else {
            $fileList = array(
                DIRECTORY_SEPARATOR => scandir(Bridge_Loader::getInstance()->getCurrentPath())
            );
        }

        return $fileList;

    }

    abstract protected function getConfigFromConfigFiles();

    abstract public function detect();

    abstract public function detectExtensions();

    abstract public function getImageDir();

    abstract public function getSiteUrl();

    abstract protected function getDbConfigPath();

    abstract protected function getVersionConfigPath();

    public function resolveRedirect()
    {
        return array(
            'entity' => '',
            'id' => 0
        );
    }

    public function handleRedirect($entity, $id)
    {
        return '';
    }

    public function getAccessKey()
    {
        return '';
    }

}

?><?php
class Bridge_Module_Cms_WordPress_WordPress3 extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'wp-config.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'wp-includes' . DIRECTORY_SEPARATOR . 'version.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {

        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        ob_start();
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        $config = array();
        $config['version'] = isset($wp_version) ? $wp_version : 'unknown';
        $config['CMSType'] = 'WordPress';
        $config['db']['host'] = defined('DB_HOST') ? constant('DB_HOST') : 'localhost';
        $config['db']['user'] = defined('DB_USER') ? constant('DB_USER') : 'root';
        $config['db']['password'] = defined('DB_PASSWORD') ? constant('DB_PASSWORD') : '';
        $config['db']['dbname'] = defined('DB_NAME') ? constant('DB_NAME') : 'wordpress';
        $config['db']['dbprefix'] = isset($table_prefix) ? $table_prefix : '';
        $config['db']['driver'] = 'mysqli'; // hardcoded database scheme

        return $config;
    }

    protected function getOptionValue($optionName)
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            "
                SELECT `option_value`
                FROM `%s`
                WHERE `option_name` = '%s'
            ",
            $this->prefixTable('options'),
            $optionName
        );

        $option = $db->fetchOne($sql);

        return $option;
    }

    public function getImageDir()
    {
        $optImgDirectory = DIRECTORY_SEPARATOR . ltrim($this->getOptionValue('upload_path'), DIRECTORY_SEPARATOR);

        $path = DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads';
        if (!empty($optImgDirectory) && $optImgDirectory != DIRECTORY_SEPARATOR) {
            $path = Bridge_Loader::getInstance()->getFs()->getLocalRelativePath($optImgDirectory);
        }

        return $path;
    }

    public function getSiteUrl()
    {
        $siteUrl = $this->getOptionValue('siteurl');

        return empty($siteUrl) ? '' : $siteUrl;
    }

    public function detectExtensions()
    {
        $plugins = array();
        $pluginsStr = $this->getOptionValue('active_plugins');

        $activePlugins = unserialize($pluginsStr);
        if (!$activePlugins) {
            return $plugins;
        }

        return $activePlugins;
    }

    public function getAccessKey()
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            "
                SELECT `option_value`
                FROM `%s`
                WHERE `option_name` = 'cms2cms-key'
            ",
            $this->prefixTable('cms2cms_options')
        );

        $key = $db->fetchOne($sql);
        if (!$key) {
            return null;
        }

        return $key;
    }

}


//some wordpresses call this function at start, we declare it
function add_filter($a, $b){}

?><?php
abstract class Bridge_Module_Cms_Joomla_Base extends Bridge_Module_Cms_Abstract
{

    public function detect()
    {
        $config = $this->getDbConfigPath();
        $version = $this->getVersionConfigPath();

        return file_exists($config) && file_exists($version);
    }

    public function getImageDir()
    {
        return '/images';
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function getAccessKey()
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            "
                SELECT `option_value`
                FROM `%s`
                WHERE `option_name` = 'cms2cms-key'
            ",
            $this->prefixTable('cms2cms_options')
        );

        $key = $db->fetchOne($sql);
        if (!$key) {
            return null;
        }

        return $key;
    }
}

?><?php
class Bridge_Module_Cms_Joomla_Joomla15 extends Bridge_Module_Cms_Joomla_Base
{

    protected function getDbConfigPath()
    {
        $config = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'configuration.php';

        return $config;
    }

    protected function getVersionConfigPath()
    {
        $version = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'version.php';

        return $version;
    }

    protected function getConfigFromConfigFiles()
    {

        $config = $this->getDbConfigPath();
        $version = $this->getVersionConfigPath();

        define('JPATH_BASE', Bridge_Loader::getInstance()->getCurrentPath());
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include ($config);
        /** @noinspection PhpIncludeInspection */
        include ($version);
        ob_clean();

        if (!class_exists('JVersion')) {
            return false;
        }

        /** @noinspection PhpUndefinedClassInspection */
        $joomlaVersion = new JVersion();
        /** @noinspection PhpUndefinedClassInspection */
        $joomlaConfig = new JConfig();

        $config = array();
        $config['CMSType'] = 'Joomla';
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        $config['version'] = $joomlaVersion->RELEASE . '.' . $joomlaVersion->DEV_LEVEL;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['seo']['is_use'] = $joomlaConfig->sef;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['seo']['sef_rewrite'] = $joomlaConfig->sef_rewrite;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['seo']['sef_suffix'] = $joomlaConfig->sef_suffix;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['host'] = $joomlaConfig->host;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['user'] = $joomlaConfig->user;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['password'] = $joomlaConfig->password;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['dbname'] = $joomlaConfig->db;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['dbprefix'] = $joomlaConfig->dbprefix;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['driver'] = $joomlaConfig->dbtype;

        return $config;
    }

    public function detectExtensions()
    {
        if (!class_exists('JVersion')) {
            return array();
        }

        /** @noinspection PhpUndefinedClassInspection */
        $joomlaVersion = new JVersion();
        /** @noinspection PhpUndefinedFieldInspection */
        if (version_compare($joomlaVersion->RELEASE, '1.5')) {
            $extensions = $this->detect16Extensions();
        }
        else {
            $extensions = $this->detect15Extensions();
        }

        return $extensions;

    }

    public function detect15Extensions()
    {
        $db = Bridge_Db::getDbAdapter();

        $sqlModules = sprintf(
            '
                SELECT `module`, `published`
                FROM `%s`
            ',
            $this->prefixTable('modules')
        );

        $sqlPlugins = sprintf(
            '
                    SELECT `name`, `published`
                    FROM `%s`
                ',
            $this->prefixTable('plugins')
        );

        $sqlComponents = sprintf(
            '
                    SELECT `name`, `enabled` AS published
                    FROM `%s`
                ',
            $this->prefixTable('components')
        );

        $modules = $db->fetchAll($sqlModules, 'module');
        $plugins = $db->fetchAll($sqlPlugins, 'name');
        $components = $db->fetchAll($sqlComponents, 'name');

        return array_merge($modules, $plugins, $components);

    }

    public function detect16Extensions()
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            '
                SELECT `name`, `enabled`
                FROM `%s`
            ',
            $this->prefixTable('extensions')
        );

        return $db->fetchAll($sql, 'name');

    }


}
?><?php
class Bridge_Module_Cms_Joomla_Joomla17 extends Bridge_Module_Cms_Joomla_Joomla15
{

    protected function getVersionConfigPath()
    {
        $version = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'version.php';

        return $version;
    }

    protected function getConfigFromConfigFiles()
    {
        $config = $this->getDbConfigPath();
        $version = $this->getVersionConfigPath();

        define('_JEXEC', Bridge_Loader::getInstance()->getCurrentPath());
        define('JPATH_PLATFORM', 1);
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include ($config);
        /** @noinspection PhpIncludeInspection */
        include ($version);
        ob_clean();

        if (!class_exists('JVersion')) {
            return false;
        }

        /** @noinspection PhpUndefinedClassInspection */
        $joomlaVersion = new JVersion();
        /** @noinspection PhpUndefinedClassInspection */
        $joomlaConfig = new JConfig();

        $config = array();
        $config['CMSType'] = 'Joomla';
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        $config['version'] = $joomlaVersion->RELEASE . '.' . $joomlaVersion->DEV_LEVEL;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['seo']['is_use'] = $joomlaConfig->sef;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['seo']['sef_rewrite'] = $joomlaConfig->sef_rewrite;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['seo']['sef_suffix'] = $joomlaConfig->sef_suffix;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['host'] = $joomlaConfig->host;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['user'] = $joomlaConfig->user;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['password'] = $joomlaConfig->password;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['dbname'] = $joomlaConfig->db;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['dbprefix'] = $joomlaConfig->dbprefix;
        /** @noinspection PhpUndefinedFieldInspection */
        $config['db']['driver'] = $joomlaConfig->dbtype;

        return $config;
    }

}

?><?php
class Bridge_Module_Cms_Joomla_Joomla25 extends Bridge_Module_Cms_Joomla_Joomla17
{

    protected function getVersionConfigPath()
    {
        $version = Bridge_Loader::getInstance()->getCurrentPath()
            . DIRECTORY_SEPARATOR . 'libraries'
            . DIRECTORY_SEPARATOR . 'cms'
            . DIRECTORY_SEPARATOR . 'version'
            . DIRECTORY_SEPARATOR . 'version.php';

        return $version;
    }
}

?><?php
class Bridge_Module_Cms_Drupal_Drupal5 extends  Bridge_Module_Cms_Abstract
{

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath()
            . DIRECTORY_SEPARATOR . 'sites'
            . DIRECTORY_SEPARATOR . 'default'
            . DIRECTORY_SEPARATOR . 'settings.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath()
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'system'
            . DIRECTORY_SEPARATOR . 'system.module';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $db_prefix = '';

        ob_start();
        /** @noinspection PhpIncludeInspection */
        include ($dbConfig);
        /** @noinspection PhpIncludeInspection */
        include($versionConfig);
        ob_clean();

        if (!isset($db_url) || !defined('VERSION')) {
            Bridge_Exception::ex('Can not detect config', null);
            return false;
        }

        if (is_array($db_url)){
            $db_url = $db_url['default'];
        }

        $data = parse_url($db_url);
        /** @noinspection PhpUndefinedConstantInspection */
        $config['version'] = VERSION;
        $config['CMSType'] = 'Drupal';
        $config['db']['host'] = urldecode($data['host']);
        $config['db']['user'] = urldecode($data['user']);
        $config['db']['password'] = isset($data['pass']) ? urldecode($data['pass']) : '';
        $config['db']['dbname'] = str_replace('/', '', urldecode($data['path']));
        $config['db']['dbprefix'] = $db_prefix;
        $config['db']['driver'] = $data['scheme'];

        return $config;
    }

    public function getImageDir()
    {
        return '/sites/default/files';
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            '
                SELECT `name`, `status`
                FROM `%s` WHERE `status` = 1
            ',
            $this->prefixTable('system')
        );

        return $db->fetchAll($sql, 'name');
    }

}
?>
<?php
class Bridge_Module_Cms_Drupal_Drupal6 extends  Bridge_Module_Cms_Drupal_Drupal5
{

    protected function prefixTable($tableName)
    {
        $config = $this->getConfig();

        $prefix = '';
        if (isset($config['db']['dbprefix'])) {
            $prefix = $config['db']['dbprefix'];
        }

        if (is_array($prefix)){
            if (isset($prefix[$tableName])){
                $prefix = $prefix[$tableName];
            }
            elseif (isset($prefix['default'])) {
                $prefix = $prefix['default'];
            }
            else {
                $prefix = '';
            }
        }

        return $prefix . $tableName;
    }

}
?><?php
class Bridge_Module_Cms_Drupal_Drupal7 extends Bridge_Module_Cms_Drupal_Drupal6
{
    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath()
            . DIRECTORY_SEPARATOR . 'includes'
            . DIRECTORY_SEPARATOR . 'bootstrap.inc';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();
        $databasesDir = Bridge_Loader::getInstance()->getCurrentPath()
            . DIRECTORY_SEPARATOR . 'includes'
            . DIRECTORY_SEPARATOR . 'database';

        return file_exists($dbConfig) && file_exists($versionConfig)  && file_exists($databasesDir);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        ob_start();
        /** @noinspection PhpIncludeInspection */
        include ($dbConfig);
        /** @noinspection PhpIncludeInspection */
        include ($versionConfig);
        ob_clean();

        if (!isset($databases) || !defined('VERSION')) {
            Bridge_Exception::ex('Can not detect config', null);
            return false;
        }

        $config = array();
        /** @noinspection PhpUndefinedConstantInspection */
        $config['version'] = VERSION;
        $config['CMSType'] = 'Drupal';
        $config['db']['host'] = $databases['default']['default']['host'];
        $config['db']['user'] = $databases['default']['default']['username'];
        $config['db']['password'] = $databases['default']['default']['password'];
        $config['db']['dbname'] = $databases['default']['default']['database'];
        $config['db']['dbprefix'] = $databases['default']['default']['prefix'];
        $config['db']['driver'] = $databases['default']['default']['driver'];

        return $config;
    }

    public function getAccessKey()
    {
        $db = Bridge_Db::getDbAdapter();
        $sql = sprintf(
            "
                SELECT `value`
                FROM `%s`
                WHERE `name` = 'cms2cms-key'
            ",
            $this->prefixTable('variable')
        );

        $key = unserialize($db->fetchOne($sql));
        if (!$key) {
            return null;
        }

        return $key;
    }
}
?><?php
abstract class Bridge_Module_Cms_Typo3_Base extends Bridge_Module_Cms_Abstract
{

    public function getImageDir()
    {
        // Relative path to the images directory
        $imgDir = DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pics';

        return $imgDir;
    }

}

?><?php
class Bridge_Module_Cms_Typo3_Typo34 extends Bridge_Module_Cms_Typo3_Base
{
    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'typo3conf' . DIRECTORY_SEPARATOR . 'localconf.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $verConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 't3lib' . DIRECTORY_SEPARATOR . 'class.t3lib_div.php';

        return $verConfig;
    }

    protected function getConfigFromConfigFiles()
    {
        // Declare database settings variables,
        // which are to be included from config files:
        $TYPO3_CONF_VARS = null;
        $typo_db_host = null;
        $typo_db_username = null;
        $typo_db = null;

        ob_start();
        define ('TYPO3_MODE', true);
        /** @noinspection PhpIncludeInspection */
        include($this->getVersionConfigPath());
        /** @noinspection PhpIncludeInspection */
        include($this->getDbConfigPath());
        ob_clean();

        if (is_null($TYPO3_CONF_VARS) || is_null($typo_db_host)
            || is_null($typo_db_username) || is_null($typo_db)
        ) {
            Bridge_Exception::ex('Can not detect config for Typo3', null);

            return false;
        }

        $config['version'] = $TYPO3_CONF_VARS['SYS']['compat_version'];
        $config['CMSType'] = 'Typo3';

        $config['db']['host'] = $typo_db_host;
        $config['db']['user'] = $typo_db_username;
        if (!isset($typo_db_password)) {
            $typo_db_password = ''; // The $typo_db_password is not declared if the password is empty
        }
        $config['db']['password'] = $typo_db_password;
        $config['db']['dbname'] = $typo_db;
        $config['db']['dbprefix'] = ''; // No support for table prefix out of the box
        $config['db']['driver'] = 'mysqli'; // hardcoded database scheme

        return $config;
    }

    public function detect()
    {
        return file_exists($this->getDbConfigPath()) && file_exists($this->getVersionConfigPath());
    }

    public function detectExtensions()
    {
        return array();
    }

    public function getSiteUrl()
    {
        $config = $this->getConfig();

        return $config['siteurl'];
    }
}

?><?php
class Bridge_Module_Cms_Typo3_Typo36 extends Bridge_Module_Cms_Typo3_Base
{

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'typo3conf' . DIRECTORY_SEPARATOR . 'LocalConfiguration.php';

        return $dbConfig;
    }

    protected function getConfigFromConfigFiles()
    {
        /** @noinspection PhpIncludeInspection */
        $Typo3Config = require $this->getDbConfigPath();

        if (!isset($Typo3Config) || !isset($Typo3Config['SYS'])) {
            Bridge_Exception::ex('Can not detect config for Typo3', null);
            return false;
        }

        $config['version'] = $Typo3Config['SYS']['compat_version'];
        $config['CMSType'] = 'Typo3';
        $config['db']['host'] = $Typo3Config['DB']['host'];
        $config['db']['user'] = $Typo3Config['DB']['username'];
        $config['db']['password'] = $Typo3Config['DB']['password'];
        $config['db']['dbname'] = $Typo3Config['DB']['database'];
        $config['db']['dbprefix'] = ''; // No support for table prefix out of the box
        $config['db']['driver'] = 'mysqli'; // hardcoded database scheme

        return $config;
    }

    public function detect()
    {
        return file_exists($this->getDbConfigPath());
    }

    public function detectExtensions()
    {
        return array();
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pics';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    protected function getVersionConfigPath()
    {
        return false;
    }
}
?><?php
class Bridge_Module_Cms_phpBb_phpBb extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'config.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'constants.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        define('IN_PHPBB', true);
        ob_start();
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        if (!isset($dbhost) || !isset($dbuser)
            || !isset($dbpasswd) || !isset($dbname) || !isset($table_prefix)
        ) {
            Bridge_Exception::ex('Can not detect config for phpBB', null);

            return false;
        }

        $config['CMSType'] = 'PhpBb';
        $config['db']['host'] = $dbhost;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['user'] = $dbuser;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['password'] = $dbpasswd;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['dbname'] = $dbname;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['dbprefix'] = $table_prefix;
        $config['db']['driver'] = isset($dbms) ? $dbms : 'mysqli';
        if (defined('PHPBB_VERSION')) {
            $config['version'] = constant('PHPBB_VERSION');
        }
        else {
            $dbAdapter = Bridge_Db::getAdapter();
            $dbAdapter->connect($dbhost, $dbuser, $dbpasswd, $dbname);
            $config['version'] = '2' . $dbAdapter->fetchOne(
                    'SELECT `config_value` from ' . $table_prefix . 'config
                 WHERE `config_name` = \'version\''
                );

        }

        return $config;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'files';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        return array();
    }
}

?><?php
class Bridge_Module_Cms_Vbulletin_Vbulletin4 extends Bridge_Module_Cms_Abstract
{

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class_core.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        ob_start();
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        if (empty($config)) {
            Bridge_Exception::ex('Can not detect config for phpBB', null);

            return false;
        }

        $cms2cmsConfig['CMSType'] = 'Vbulletin';
        $cms2cmsConfig['db']['host'] = $config['MasterServer']['servername'];
        $cms2cmsConfig['db']['user'] = $config['MasterServer']['username'];
        $cms2cmsConfig['db']['password'] = $config['MasterServer']['password'];
        $cms2cmsConfig['db']['dbname'] = $config['Database']['dbname'];
        $cms2cmsConfig['db']['dbprefix'] = $config['Database']['tableprefix'];
        $cms2cmsConfig['db']['driver'] = $config['Database']['dbtype'];
        $cms2cmsConfig['version'] = constant('FILE_VERSION');

        return $cms2cmsConfig;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'images';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        return array();
    }
}

?>
<?php
class Bridge_Module_Cms_IPBoard_IPBoard extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'conf_global.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        return false;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        return file_exists($dbConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);

        define('IN_IPBOARD', true);
        ob_start();
        eval ($dbConfigContent);
        ob_clean();

        if (!isset($INFO['sql_host']) || !isset($INFO['sql_user'])
            || !isset($INFO['sql_pass']) || !isset($INFO['sql_database']) || !isset($INFO['sql_tbl_prefix'])
        ) {
            Bridge_Exception::ex('Can not detect config for IPBoard', null);
            return false;
        }

        $config['CMSType'] = 'IPBoard';
        $config['db']['host'] = $INFO['sql_host'];
        $config['db']['user'] = $INFO['sql_user'];
        $config['db']['password'] = $INFO['sql_pass'];
        $config['db']['dbname'] = $INFO['sql_database'];
        $config['db']['dbprefix'] = $INFO['sql_tbl_prefix'];
        $config['db']['driver'] = isset($INFO['sql_driver']) ? $INFO['sql_driver'] : 'mysqli';
        if ($versionConfig) {
            $config['version'] = $versionConfig;
        }
        else {
            $dbAdapter = Bridge_Db::getAdapter();
            $dbAdapter->connect($INFO['sql_host'], $INFO['sql_user'], $INFO['sql_pass'], $INFO['sql_database']);
            $config['version'] = $dbAdapter->fetchOne(
                    'SELECT `app_version` from ' . $INFO['sql_tbl_prefix'] . 'core_applications
                 WHERE `app_id` = \'1\''
                );

        }

        return $config;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'uploads';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        return array();
    }
}

?><?php
class Bridge_Module_Cms_MyBB_MyBB extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'config.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'class_core.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        define('IN_MYBB', true);
        ob_start();
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        if (!isset($config['database']['hostname']) || !isset($config['database']['username'])
            || !isset($config['database']['password']) || !isset($config['database']['database']) || !isset($config['database']['table_prefix'])
        ) {
            Bridge_Exception::ex('Can not detect config for MyBB', null);

            return false;
        }

        $config['CMSType'] = 'MyBB';
        $config['db']['host'] = $config['database']['hostname'];
        $config['db']['user'] = $config['database']['username'];
        $config['db']['password'] = $config['database']['password'];
        $config['db']['dbname'] = $config['database']['database'];
        $config['db']['dbprefix'] = $config['database']['table_prefix'];
        $config['db']['driver'] = isset($config['database']['type']) ? $config['database']['type'] : 'mysqli';
        /** @noinspection PhpUndefinedClassInspection */
        $myBB = new MyBB();
        /** @noinspection PhpUndefinedFieldInspection */
        $config['version'] = $myBB->version;

        return $config;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'uploads';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        return array();
    }
}

?><?php
class Bridge_Module_Cms_SMF_SMF extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'Settings.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        return false;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        return file_exists($dbConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);

        define('IN_SMF', true);
        ob_start();
        eval ($dbConfigContent);
        ob_clean();

        if (!isset($db_server) || !isset($db_user)
            || !isset($db_passwd) || !isset($db_name) || !isset($db_prefix)
        ) {
            Bridge_Exception::ex('Can not detect config for SMF', null);

            return false;
        }

        $config['CMSType'] = 'SMF';
        $config['db']['host'] = $db_server;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['user'] = $db_user;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['password'] = $db_passwd;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['dbname'] = $db_name;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['dbprefix'] = $db_prefix;
        $config['db']['driver'] = isset($db_type) ? $db_type : 'mysqli';
        if ($versionConfig) {
            $config['version'] = $versionConfig;
        }
        else {
            $dbAdapter = Bridge_Db::getAdapter();
            $dbAdapter->connect($db_server, $db_user, $db_passwd, $db_name);
            $config['version'] = $dbAdapter->fetchOne(
                'SELECT `value` from ' . $db_prefix . 'settings
                 WHERE `variable` = \'smfVersion\''
            );

        }

        return $config;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'attachments';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        return array();
    }
}

?><?php
class Bridge_Module_Cms_b2evolution_b2evolution extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . '_basic_config.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . '_application.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        define('EVO_CONFIG_LOADED', true);
        $function = 'function T_($string){return $string;}';
        ob_start();
        eval ($function);
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        if (!isset($db_config['host']) || !isset($db_config['user'])
            || !isset($db_config['password']) || !isset($db_config['name']) || !isset($tableprefix)
        ) {
            Bridge_Exception::ex('Can not detect config for b2evolution', null);

            return false;
        }

        $config['CMSType'] = 'B2evolution';
        $config['db']['host'] = $db_config['host'];
        $config['db']['user'] = $db_config['user'];
        $config['db']['password'] = $db_config['password'];
        $config['db']['dbname'] = $db_config['name'];
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['dbprefix'] = $tableprefix;
        $config['db']['driver'] = 'mysql';
        /** @noinspection PhpUndefinedVariableInspection */
        $config['version'] = $app_version;

        return $config;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'media';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        $plugins = array();
        $db = Bridge_Db::getDbAdapter();

        $version = substr(0,1,$this->config['version']);

        if($version > 4) {
            $pluginsData = $db->fetchAll(
                "
                SELECT DISTINCT
                  blog_type
                FROM " . $this->prefixTable('blogs') . "
                WHERE blog_type = 'photo' OR blog_type = 'forum'
                "
            );
            foreach($pluginsData as $p){
                if($p->blog_type == 'photo'){
                    $plugins[] = 'Photoblog';
                }
                if($p->blog_type == 'forum'){
                    $plugins[] = 'Forum';
                }
            }
        }

        return $plugins;
    }
}

?><?php
class Bridge_Module_Cms_e107_e107 extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'e107_config.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'e107_admin' . DIRECTORY_SEPARATOR . 'ver.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        define('e107_INIT', true);

        ob_start();
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        if (!isset($mySQLserver) || !isset($mySQLuser)
            || !isset($mySQLpassword) || !isset($mySQLdefaultdb) || !isset($mySQLprefix)
        ) {
            Bridge_Exception::ex('Can not detect config for e107', null);

            return false;
        }

        $config['CMSType'] = 'E107';
        $config['db']['host'] = $mySQLserver;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['user'] = $mySQLuser;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['password'] = $mySQLpassword;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['dbname'] = $mySQLdefaultdb;
        /** @noinspection PhpUndefinedVariableInspection */
        $config['db']['dbprefix'] = $mySQLprefix;
        $config['db']['driver'] = 'mysql';
        /** @noinspection PhpUndefinedVariableInspection */
        $config['version'] = $e107info['e107_version'];

        return $config;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'e107_images';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        return array();
    }
}

?><?php
class Bridge_Module_Cms_dle_dle extends Bridge_Module_Cms_Abstract
{

    protected $config = null;

    protected function getDbConfigPath()
    {
        $dbConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'dbconfig.php';

        return $dbConfig;
    }

    protected function getVersionConfigPath()
    {
        $versionConfig = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';

        return $versionConfig;
    }

    public function detect()
    {
        $dbConfig = $this->getDbConfigPath();

        $versionConfig = $this->getVersionConfigPath();

        return file_exists($dbConfig) && file_exists($versionConfig);
    }

    protected function getConfigFromConfigFiles()
    {
        $dbConfig = $this->getDbConfigPath();
        $versionConfig = $this->getVersionConfigPath();

        $dbConfigContent = Bridge_Includer::stripIncludes($dbConfig);
        $versionConfigContent = Bridge_Includer::stripIncludes($versionConfig);

        $class = 'class db{function index(){}}';
        ob_start();
        eval ($class);
        eval ($dbConfigContent);
        eval ($versionConfigContent);
        ob_clean();

        $config['CMSType'] = 'Dle';
        $config['db']['host'] = defined('DBHOST') ? constant('DBHOST') : 'localhost';
        $config['db']['user'] = defined('DBUSER') ? constant('DBUSER') : 'root';
        $config['db']['password'] = defined('DBPASS') ? constant('DBPASS') : '';
        $config['db']['dbname'] = defined('DBNAME') ? constant('DBNAME') : 'dle';
        $config['db']['dbprefix'] = defined('PREFIX') ? constant('PREFIX') . '_' : '';
        $config['db']['driver'] = 'mysql';
        $config['version'] = $config['version_id'];

        return $config;
    }

    public function getImageDir()
    {
        $imgDir = Bridge_Loader::getInstance()->getCurrentPath() . DIRECTORY_SEPARATOR . 'uploads';

        return $imgDir;
    }

    public function getSiteUrl()
    {
        return '';
    }

    public function detectExtensions()
    {
        return array();
    }
}

?>