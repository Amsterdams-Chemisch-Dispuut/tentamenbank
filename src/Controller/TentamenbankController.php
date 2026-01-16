<?php

namespace Drupal\tentamenbank\Controller;

use Drupal\Core\Controller\ControllerBase;
use Aws\S3\S3Client;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TentamenbankController extends ControllerBase {

  public function getTitle($study = '', $subject = '') {
    return $subject;
  }

  public function mainPage() {
    // 0. ACCESS CHECK: Only logged-in users
    if (\Drupal::currentUser()->isAnonymous()) {
      $login_url = Url::fromRoute('user.login', [], [
        'query' => \Drupal::destination()->getAsArray(),
      ]);

      return new RedirectResponse($login_url->toString());
    }

    // 1. Get User & Student Number
    $user = \Drupal::currentUser();
    $account = User::load($user->id());
    $student_number = '';

    if ($account && $account->hasField('field_uva_studentnummer') && !$account->get('field_uva_studentnummer')->isEmpty()) {
        $student_number = $account->get('field_uva_studentnummer')->value;
    }

    // 2. Get S3 Data
    try {
      $config = Settings::get('aws_s3');
      $s3 = new S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'credentials' => [
          'key'    => $config['key'],
          'secret' => $config['secret'],
        ],
      ]);

      $contents = $s3->listObjectsV2([
        'Bucket' => $config['bucket'],
        'Prefix' => 'tentamenbank/',
      ]);

      $all_subjects = $this->homePage($contents);
    } catch (\Exception $e) {
      \Drupal::logger('tentamenbank')->error('S3 Error: @error', ['@error' => $e->getMessage()]);
      return ['#markup' => "S3 Error: " . $e->getMessage()];
    }

    // 3. Get Enrolled Courses
    $my_courses = [];
    $enrolled_ids = [];

    if (!empty($student_number)) {
        $enrolled_ids = $this->getEnrolledCourses($student_number);

        foreach ($all_subjects as $subject) {
            // Normalize: remove spaces, uppercase
            $s3_id = strtoupper(trim($subject['course_id']));
            
            if (!empty($s3_id) && in_array($s3_id, $enrolled_ids)) {
                $my_courses[] = $subject;
            }
        }
    }

    return [
      '#theme' => 'tentamenbank',
      '#subjects' => $all_subjects,
      '#my_courses' => $my_courses,
      '#student_id' => $student_number, 
      '#attached' => [
        'library' => ['tentamenbank/tentamenbank'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['user:' .$user->id()],
      ]
    ];
  }

  public function myPage($study = '', $subject = '') {
    // 0. ACCESS CHECK: Only logged-in users
    if (\Drupal::currentUser()->isAnonymous()) {
      $login_url = Url::fromRoute('user.login', [], [
        'query' => \Drupal::destination()->getAsArray(),
      ]);

      return new RedirectResponse($login_url->toString());
    }

    try {
      $config = Settings::get('aws_s3');
      $s3 = new S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'credentials' => [
          'key'    => $config['key'],
          'secret' => $config['secret'],
        ],
      ]);
      $contents = $s3->listObjectsV2([
        'Bucket' => $config['bucket'],
        'Prefix' => 'tentamenbank/' . urldecode($study) . '/' . urldecode($subject),
      ]);
      $exams = $this->tentamensPage($contents);
      return [
        '#theme' => 'tentamenbank_subject',
        '#exams' => $exams,
        '#attached' => ['library' => ['tentamenbank/tentamenbank']],
      ];
    } catch (\Exception $e) {
      \Drupal::logger('tentamenbank')->error('Error: @error', ['@error' => $e->getMessage()]);
      return ['#markup' => "Error: " . $e->getMessage()];
    }
  }

  // ... (Helper functions getEnrolledCourses, homePage, tentamensPage remain unchanged) ...
  
  private function getEnrolledCourses($student_number) {
    try {
        $client = \Drupal::httpClient();
        $url = 'https://api.datanose.nl/Enrolments/' . $student_number;
        
        $response = $client->request('GET', $url, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json, application/xml']
        ]);
        
        $body = (string) $response->getBody();
        if (empty($body)) return [];

        $ids = [];

        // ATTEMPT 1: JSON
        $json = json_decode($body, TRUE);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
             foreach ($json as $entry) {
                 if (isset($entry['CatalogNumber'])) $ids[] = strtoupper(trim($entry['CatalogNumber']));
             }
        } 
        // ATTEMPT 2: XML
        else {
             libxml_use_internal_errors(TRUE);
             $xml = simplexml_load_string($body);
             if ($xml && isset($xml->EnrolmentEntry)) {
                 foreach ($xml->EnrolmentEntry as $entry) {
                     $ids[] = strtoupper(trim((string)$entry->CatalogNumber));
                 }
             }
             libxml_clear_errors();
        }

        return array_unique($ids);

    } catch (\Exception $e) {
        \Drupal::logger('tentamenbank')->error('API Error: @error', ['@error' => $e->getMessage()]);
        return [];
    }
  }

  private function homePage($contents) { 
    $mapping_file = 'public://tentamenbank_course_ids.json';
    $mapping_data = [];
    if (file_exists($mapping_file)) {
      $mapping_data = json_decode(file_get_contents($mapping_file), TRUE);
    }

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
                    
                    $lookup_key = htmlspecialchars_decode($subject);
                    $stored = $mapping_data[$lookup_key] ?? $mapping_data[$subject] ?? null;

                    $cid = '';
                    $displayName = $subject; 

                    if ($stored) {
                        if (is_array($stored)) {
                            $cid = $stored['id'] ?? '';
                            if (!empty($stored['name'])) $displayName = $stored['name'];
                        } else {
                            $cid = $stored;
                        }
                    }

                    $result[] = [
                        'study' => $study,
                        'subject' => $displayName,
                        'course_id' => $cid,
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

          // UPDATED: Regex now matches .pdf OR .zip (case-insensitive)
          // Group 1: Date, Group 2: Type, Group 3: Suffix, Group 4: Extension
          if (preg_match('/^(\d{4}-\d{2}-\d{2})_(.*)_(.*)\.(pdf|zip)$/i', $lastElement, $matches)) {
            $date = date_create(implode('', explode('-', $matches[1])));
            $sorting = $date->format('Y-m-d');
            $displayDate = $date->format('d M Y');
            
            $type = $matches[2];
            $extension = strtolower($matches[4]);

            if (!isset($exams[$displayDate])) {
              $exams[$displayDate] = [
                  'sorting' => $sorting,
                  'date' => $displayDate,
                  'type' => '',
                  'questions' => '',
                  'questions_label' => 'Questions', // Default label
                  'answers' => '',
              ];
            }

            if ($type == 'Answers') {
                $exams[$displayDate]['answers'] = $key;
            } else {
                $exams[$displayDate]['questions'] = $key;
                $exams[$displayDate]['type'] = $type;

                // Check extension to update the label
                if ($extension === 'zip') {
                    $exams[$displayDate]['questions_label'] = 'Questions (zip)';
                }
            }
          }
      }
    }
    usort($exams, function($a, $b) { return strcmp($b['sorting'], $a['sorting']); });
    return $exams;
  }
}