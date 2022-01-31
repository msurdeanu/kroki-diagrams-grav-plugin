<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

class KrokiPlugin extends Plugin {

  public static function getSubscribedEvents() {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0]
    ];
  }

  public function onPluginsInitialized() {
    if ($this->isAdmin()) {
      $this->active = false;
      return;
    }
    $this->enable([
      'onPageContentRaw' => ['onPageContentRaw', 0]
    ]);
  }

  public function onPageContentRaw(Event $event) {
    $page = $event['page'];
    $pageDirPath = dirname($page->filePath());
    $usedFiles = array();
    $callback = function ($matches) use (&$pageDirPath, &$usedFiles) {
      $id = $matches[1];
      $type = $matches[2];
      $body = $this->onBodySanitize($matches[3]);
      $hash = sha1($id . "|" . $type . "|" . $body);
      $extension = $this->config->get('plugins.kroki.extension');
      $localFileName = "diag_" . $id . "_" . $hash . "." . $extension;
      $localFilePath = $pageDirPath . "/" . $localFileName;
      if (!file_exists($localFilePath)) {
        $kroki = $this->config->get('plugins.kroki.url') . "/" . $type . "/" . $extension . "/" . $this->onCompressAndEncode($body);
        $content = file_get_contents($kroki);
        if ($content !== false) {
          @file_put_contents($localFilePath, $content);
          array_push($usedFiles, $localFileName);
          return "![" . $id . "](" . $localFileName . "){.center}";
        } else {
          return "[An error occurred during Kroki diagram drawing!](" . $kroki . ")";
        }
      }
      array_push($usedFiles, $localFileName);
      return "![" . $id . "](" . $localFileName . "){.center}";
    };

    $raw = $page->getRawContent();
    $raw = $this->onParseAndInject($raw, $callback);
    $this->onCleanOldFiles($usedFiles, $pageDirPath);
    $page->setRawContent($raw);
  }

  protected function onBodySanitize($body) {
    return trim($body);
  }

  protected function onCompressAndEncode($content) {
    return rtrim(strtr(base64_encode(gzcompress($content)), '+/', '-_'), '=');
  }

  protected function onParseAndInject($content, $callback) {
    return preg_replace_callback('/\[kroki id="([a-zA-Z0-9]+)" type="([a-z0-9]+)"\]([\s\S]*?)\[\/kroki\]/', $callback, $content);
  }
  
  protected function onCleanOldFiles($usedFiles, $pageDirPath) {
    foreach (scandir($pageDirPath) as $pageFile) {
      if (str_starts_with($pageFile, "diag_") && !in_array($pageFile, $usedFiles)) {
        @unlink($pageDirPath . "/" . $pageFile);
      }
    }
  }

}
