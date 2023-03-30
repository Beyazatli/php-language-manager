<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

!defined('LM_DIR') && define('LM_DIR', __DIR__);
!defined('LM_DS') && define('LM_DS', DIRECTORY_SEPARATOR);
!defined('LM_PASSWORD_MD5') && define('LM_PASSWORD_MD5', md5('Beyazatli!Language1Manager'));

// LM = Language Manager
// LM_DIR = Current directory
// LM_DS = Directory separator

class LanguageManager {
  protected $mainPath = 'languages';
  protected $metaFile = 'meta.372717.json';

  function __construct() {
    if (!$this->isReady()) {
      $this->setup();
    }
  }

  // olmek bize ozel :}
  private function die($err) {
    die("[LANGUAGE_MANAGER]: ".(string) $err);
  }

  // withoutDs return main path without LM_DS and path
  protected function path($path = '', $withoutDs = false) {
    return LM_DIR.LM_DS.$this->mainPath.($withoutDs ? '' : LM_DS.$path);
  }

  public function isReady() {
    if (!is_dir($this->path())) return false;
    if (!file_exists($this->getMetaFilePath())) return false;
    return true;
  }

  public function getMetaFilePath() {
    return $this->path($this->metaFile);
  }

  protected function getMetaFile() {
    return $this->readFile($this->getMetaFilePath(), true);
  }

  protected function createOrUpdateMetaFile($data = null) {
    if (empty($data)) {
      $metaFile = [
        '__comment__' => '[EN]: Don\'t fix this file from here or it will be corrupted. | [TR] Bu dosyayi buradan duzenlemeyin, aksi takdirde bozulur.',
        'lastUpdate' => time(),
        'createTime' => time(),
        'defaultLanguage' => null,
        'languages' => [],
      ];
      return $this->writeFile($this->getMetaFilePath(), $metaFile, true);
    }
    $currentMetaFile = $this->getMetaFile();
    $keys = ['lastUpdate', 'createTime', 'defaultLanguage', 'languages'];
    $isBroken = false;
    foreach($keys as $key) {
      if (!array_key_exists($key, $currentMetaFile)) {
        $this->writeFile($this->path('meta.backup.'.time().'.json'), $currentMetaFile, true);
        $isBroken = true;
        break;
      }
    }

    if ($isBroken) {
      $this->createOrUpdateMetaFile(null);
      $this->die('Metafile is broken and will be rebuilt when the page is refreshed / closed. A backup of the old file has been saved, please contact your developer.');
    }

    $data['lastUpdate'] = time();

    $this->writeFile($this->getMetaFilePath(), $data, true);
    return true;
  }
  
  protected function setup() {
    $this->createFolderStructure();
    $this->createOrUpdateMetaFile();
    $this->downloadIcons();
    $this->createInitialLanguage();
  }

  public function writeFile($fullPath, $data, $jsonEncode = false) {
    $fp = fopen($fullPath, 'w');
    fwrite($fp, $jsonEncode ? json_encode($data) : $data);
    fclose($fp);
    return true;
  }

  public function readFile($fullPath, $jsonDecode = false) {
    if (!file_exists($fullPath)) return false;
    $fp = fopen($fullPath, "r") or $this->die("Unable to open file!");
    $content = fread($fp, filesize($fullPath));
    fclose($fp);
    return $jsonDecode ? json_decode($content, true) : $content;
  }

  protected function createFolderStructure() {
    if (!is_dir($this->path())) {
      mkdir($this->path(), 0755);
      $fp = fopen($this->path('index.php'),'w');
      fwrite($fp, '<?php exit(http_response_code(403)); ?>');
      fclose($fp);
    }
    if (!is_dir($this->path('icons'))) {
      if (!mkdir($this->path('icons'), 0755, true)) {
        if (!mkdir($this->path('icons'), 0755)) {
          $this->die('Failed to create icons folder.');
        }
      }
    }
    $fp = fopen($this->path('icons/index.php'),'w');
    fwrite($fp, '<?php exit(http_response_code(403)); ?>');
    fclose($fp);
  }

  protected function downloadIcons() {
    $this->iconsFileName = 'icons'.time().'.zip';
    $fh = fopen($this->iconsFileName, 'w');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://github.com/Beyazatli/php-language-manager/raw/main/flags.zip"); 
    curl_setopt($ch, CURLOPT_FILE, $fh); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if(curl_exec($ch) === false) {
      curl_close($ch);
      fclose($fh);
      $this->die(curl_error($ch));
    }
    curl_close($ch);
    fclose($fh);

    $zipArchive = new ZipArchive();
    $result = $zipArchive->open($this->iconsFileName);
    if ($result === TRUE) {

      $zipArchive ->extractTo($this->path('icons'));
      $zipArchive ->close();
      unlink($this->iconsFileName);
      $this->iconsFileName = null;
    } else {
      $ths->die('Error: failed download icons from (Beyazatli/php-language-manager/raw/main/flags.zip)');
    }
  }

  protected function languageExists($languageCode) {
    if (!$languageCode) return false;
    if (file_exists($this->path($languageCode.'.json'))) return true;
    return false;
  }

  public function setDefaultLanguage($languageCode) {
    if (!$this->languageExists($languageCode)) return false;
    $meta = $this->getMetaFile();
    $key = array_search($languageCode, array_column($meta['languages'], 'code'));
    $meta['defaultLanguage'] = $meta['languages'][$key]['name'];
    if (empty($meta['defaultLanguage'])) return false;
    return $this->createOrUpdateMetaFile($meta);
  }

  public function createLanguage($name, $languageCode = null, $flag = null, $isActive = true) {
    if ($this->languageExists($languageCode)) return false;
    $currentMetaFile = $this->getMetaFile();

    $metaLanguage = [
      'name' => $name,
      'code' => $languageCode,
      'flag' => $flag,
      'isActive' => $isActive
    ];

    $languageData = [];

    // if (!empty($currentMetaFile['defaultLanguage'])) { // fill new language with default language data
    //   $defaultLanguage = $this->getDefaultLanguage();
    //   $languageData = $defaultLanguage;
    // }

    $currentMetaFile['defaultLanguage'] = empty($currentMetaFile['defaultLanguage']) ? $languageCode : null;

    $currentMetaFile['languages'][] = $metaLanguage;
    $this->createOrUpdateMetaFile($currentMetaFile);
    return $this->writeFile($this->path($languageCode.'.json'), $languageData, true);
  }

  public function getDefaultLanguageName() {
    $meta = $this->getMetaFile();
    return $meta['defaultLanguage'];
  }

  public function getDefaultLanguage() {
    $meta = $this->getMetaFile();
    return $this->getLanguage($meta['defaultLanguage']);
  }

  public function getLanguage($languageCode) {
    return $this->readFile($this->path($languageCode.'.json'), true);
  }

  // return false means language not found or cannot removed.
  public function removeLanguage($languageCode) {
    $currentMetaFile = $this->getMetaFile();
    $languages = $currentMetaFile['languages'];
    $langKey = array_search($languageCode, array_column($languages, 'code'));
    if (!$langKey) return false;
    unset($currentMetaFile['languages'][$langKey]);
    unlink($this->path($languageCode.'.json'));
    
    $this->createOrUpdateMetaFile($currentMetaFile);
  }

  protected function createInitialLanguage() {
    $this->createLanguage('English', 'en', 'gb.svg', true);
  }

  public function createKey($languageCode, $key, $value = null) {
    if ($this->languageExists($languageCode)) {
      $lang = $this->getLanguage($languageCode);
      if (array_key_exists($key, $lang)) return false;
      $lang[$key] = $value;
      $this->writeFile($this->path($languageCode.'.json'), $lang, true);
      return true;
    }
    return false;
  }

  public function removeKey($languageCode, $key) {
    if ($this->languageExists($languageCode)) {
      $lang = $this->getLanguage($languageCode);
      unset($lang[$key]);
      $this->writeFile($this->path($languageCode.'.json'), $lang, true);
      return true;
    }
    return false;
  }

  public function updateValue($languageCode, $key, $value) {
    if ($this->languageExists($languageCode)) {
      $lang = $this->getLanguage($languageCode);
      $lang[$key] = $value;
      $this->writeFile($this->path($languageCode.'.json'), $lang, true);
      return true;
    }
    return false;
  }

  protected function tst(string $str) {
    return trim(strip_tags(trim($str)));
  }

  protected function getHeaders() {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
      if (strpos($key, 'HTTP_') === 0) {
        $headers[str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
      }
    }
    return $headers;
  }

  public function api() {
    $json = file_get_contents('php://input');
    $body = json_decode($json);
    $op = isset($_GET['op']) ? $_GET['op'] : null;
    $token = $this->getHeaders()['Authorization'] ?: null;

    if (empty($token)) {
      $this->apiResponse(['type' => false, 'message' => 'Token cannot be empty.']);
    }

    if ($token !== LM_PASSWORD_MD5) {
      $this->apiResponse(['type' => false, 'message' => 'Authorization token is invalid.']);
    }

    switch ($op) {
      case 'createLanguage':
        $name = $this->tst($body['name']);
        $languageCode = $this->tst($body['languageCode']); // or region code :)
        $flag = $this->tst($body['flag']) ?: null;

        if (empty($name) || empty($languageCode)) {
          $this->apiResponse(['type' => false, 'message' => 'Name / (Language|Region) code cannot be empty.']);
        }

        if ($this->languageExists($languageCode)) {
          $this->apiResponse(['type' => false, 'message' => 'This (Language|Region) code already exists.']);
        }

        $createRequest = $this->createLanguage($name, $languageCode, $flag);
        if (!$createRequest) {
          $this->apiResponse(['type' => false, 'message' => 'Operation failed, please try again.']);
        }
        $this->apiResponse(['type' => true, 'message' => 'Language created successfully.']);
      break;

      case 'removeLanguage':
        // 
      break;
      
      default:
        $this->apiResponse(['type' => false, 'message' => 'Language Manager API is working now. ('.time().')']);
      break;
    }
  }

  public function apiResponse($data = ['type' => false, 'message' => null], $withExit = true) {
    header('Content-Type: application/json; charset=utf-8');
    return $withExit ? exit(json_encode($data)) : json_encode($data);
  }
}

// $lg = new LanguageManager();
// $lg->api();
