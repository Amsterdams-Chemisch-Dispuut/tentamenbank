<?php

namespace Drupal\tentamenbank\Controller;

use Drupal\Core\Controller\ControllerBase;
use Aws\S3\S3Client;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides route responses for the S3 Gallery module.
 */
class TentamenbankController extends ControllerBase {

  /**
   * Returns the title for the gallery pages.
   *
   * @param string|null $prefix
   *   The prefix for the S3 objects.
   *
   * @return string
   *   The title for the gallery page.
   */
  public function getTitle($study = '', $subject = '') {
    return $subject;
  }

  /**
   * Returns the main gallery page.
   *
   * @return array
   *   A renderable array.
   */
  public function mainPage() {
    // Require user to be logged in
    if (\Drupal::currentUser()->isAnonymous()) {
      return [
        '#markup' => t('Access denied. Please log in to view this page.'),
        '#cache' => ['max-age' => 0],
      ];
    }
    try {
      // Retrieve AWS S3 configuration from settings.php
      $config = Settings::get('aws_s3');
      $s3 = new S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'credentials' => [
          'key'    => $config['key'],
          'secret' => $config['secret'],
        ],
      ]);

      $bucket = 'acdweb-storage';
      $prefix = 'tentamenbank/'; // Ensure 'photos/' is prefixed and decode the prefix
      $contents = $s3->listObjectsV2([
        'Bucket' => $bucket,
        'Prefix' => $prefix,
      ]);

      $result = $this->homePage($contents);



      return [
        '#theme' => 'tentamenbank',
        '#subjects' => $result,
        '#attached' => [
          'library' => [
            'tentamenbank/tentamenbank',
          ],
        ],
      ];
    } catch (\Exception $e) {
      // Debugging information
      \Drupal::logger('tentamenbank')->error('Error: @error', ['@error' => $e->getMessage()]);
      return [
        '#markup' => "Error: " . $e->getMessage(),
      ];
    }
  }

  /**
   * Returns a gallery page.
   *
   * @param string|null $prefix
   *   The prefix for the S3 objects.
   *
   * @return array
   *   A renderable array.
   */
  public function myPage($study = '', $subject = '') {
    // Require user to be logged in
    if (\Drupal::currentUser()->isAnonymous()) {
      return [
        '#markup' => t('Access denied. Please log in to view this page.'),
        '#cache' => ['max-age' => 0],
      ];
    }
    try {
      // Retrieve AWS S3 configuration from settings.php
      $config = Settings::get('aws_s3');
      $s3 = new S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'credentials' => [
          'key'    => $config['key'],
          'secret' => $config['secret'],
        ],
      ]);

      $bucket = 'acdweb-storage';
      $prefix = 'tentamenbank/' . urldecode($study) . '/' . urldecode($subject); // Ensure 'photos/' is prefixed and decode the prefix
      $contents = $s3->listObjectsV2([
        'Bucket' => $bucket,
        'Prefix' => $prefix,
      ]);

      $exams = $this->tentamensPage($contents);
      
      
      return [
        '#theme' => 'tentamenbank_subject',
        '#exams' => $exams,
        '#attached' => [
          'library' => [
            'tentamenbank/tentamenbank',
          ],
        ],
      ];
    } catch (\Exception $e) {
      // Debugging information
      \Drupal::logger('tentamenbank')->error('Error: @error', ['@error' => $e->getMessage()]);
      return [
        '#markup' => "Error: " . $e->getMessage(),
      ];
    }
  }

  private function homePage($contents) { 
    $result = [];
      if (isset($contents['Contents'])) {
        $uniqueKeys = [];
        foreach ($contents['Contents'] as $content) {
            $key = htmlspecialchars($content['Key']);
            $strippedKey = substr($key, 0, strrpos($key, '/'));
    
            if (!in_array($strippedKey, $uniqueKeys)) {
                $uniqueKeys[] = $strippedKey;
    
                $splitKey = explode('/', trim($strippedKey, '/'));
                if (count($splitKey) >= 3) {
                    $study = $splitKey[1];
                    $subject = $splitKey[2];
                    $url = "/tentamenbank/" . $study . "/" . $subject;
                    $result[] = [
                        'study' => $study,
                        'subject' => $subject,
                        'url' => $url,
                    ];
                }
            }
        }
    }

      return $result;
  }

  private function tentamensPage($contents) {
    $exams = [];

    if (isset($contents['Contents'])) {
      foreach ($contents['Contents'] as $content) {
          $key = htmlspecialchars($content['Key']);
          $splitKey = explode('/', trim($key, '/'));
          $lastElement = end($splitKey);

          if (preg_match('/^(\d{4}-\d{2}-\d{2})_(.*)_(.*)\.pdf$/', $lastElement, $matches)) {
            $date = date_create(implode('', explode('-', $matches[1])));
            $sorting = $date->format('Y-m-d');
            $date = $date->format('d M Y');
            $type = $matches[2];
            $title = $matches[3];

            if (!isset($exams[$date])) {
              $exams[$date] = [
                  'sorting' => $sorting,
                  'date' => $date,
                  'type' => '',
                  'questions' => '',
                  'answers' => '',
              ];
          }
          if ($type == 'Answers') {
              $exams[$date]['answers'] = $key;
          } else {
              $exams[$date]['questions'] = $key;
              $exams[$date]['type'] = $type;
          }

          
      }
  }

  usort($exams, function($a, $b) {
    return strcmp($b['sorting'], $a['sorting']);
  });


  


  return $exams;
  }
}
}