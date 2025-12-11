<?php

namespace Drupal\tentamenbank\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Aws\S3\S3Client;

class CourseMappingForm extends FormBase {

  const MAPPING_FILE = 'public://tentamenbank_course_ids.json';

  public function getFormId() {
    return 'tentamenbank_course_mapping_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // 1. Load existing data
    $mapping = [];
    if (file_exists(self::MAPPING_FILE)) {
      $mapping = json_decode(file_get_contents(self::MAPPING_FILE), TRUE);
    }

    $s3_data = $this->getS3Data();

    $form['description'] = [
      '#markup' => '<p>Manage Course IDs and Custom Display Names.</p>',
    ];

    $form['mapping'] = [
      '#type' => 'table',
      '#header' => ['Study', 'Original Folder Name', 'Display Name (Optional)', 'Course Catalogue ID'],
      '#empty' => $this->t('No exam folders found in S3.'),
    ];

    // Sort by Study then Subject
    usort($s3_data, function($a, $b) {
        return $a['study'] <=> $b['study'] ?: $a['subject'] <=> $b['subject'];
    });

    foreach ($s3_data as $item) {
      $study = $item['study'];
      $subject = $item['subject'];
      
      // Handle legacy (string) vs new (array) JSON format
      $stored_data = $mapping[$subject] ?? [];
      if (!is_array($stored_data)) {
          $stored_data = ['id' => $stored_data]; // Convert legacy string to array
      }

      $form['mapping'][$subject]['study'] = [
        '#plain_text' => $study,
      ];

      $form['mapping'][$subject]['original_name'] = [
        '#plain_text' => $subject,
      ];
      
      // NEW: Display Name Field
      $form['mapping'][$subject]['display_name'] = [
        '#type' => 'textfield',
        '#default_value' => $stored_data['name'] ?? '', 
        '#size' => 25,
        '#attributes' => ['placeholder' => $subject], // Placeholder shows default
      ];

      $form['mapping'][$subject]['course_id'] = [
        '#type' => 'textfield',
        '#default_value' => $stored_data['id'] ?? '',
        '#size' => 10,
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('mapping');
    $data_to_save = [];

    foreach ($values as $subject => $row) {
      // Save if either ID or Name is set
      if (!empty($row['course_id']) || !empty($row['display_name'])) {
        $data_to_save[$subject] = [
            'id' => trim($row['course_id']),
            'name' => trim($row['display_name']),
        ];
      }
    }

    file_put_contents(self::MAPPING_FILE, json_encode($data_to_save, JSON_PRETTY_PRINT));
    $this->messenger()->addStatus($this->t('Course configuration saved.'));
  }

  // ... Keep getS3Data() exactly as it was before ...
  private function getS3Data() {
    // (Paste the exact getS3Data function from previous step here)
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
      $bucket = 'acdweb-storage';
      $prefix = 'tentamenbank/';
      $contents = $s3->listObjectsV2(['Bucket' => $bucket, 'Prefix' => $prefix]);
      $found = [];
      $seen = [];
      if (isset($contents['Contents'])) {
        foreach ($contents['Contents'] as $content) {
            $key = $content['Key'];
            $parts = explode('/', $key);
            if (count($parts) >= 3) {
                $study = $parts[1];
                $subject = $parts[2];
                $unique_id = $study . '|' . $subject;
                if (!empty($subject) && !isset($seen[$unique_id])) {
                    $seen[$unique_id] = true;
                    $found[] = ['study' => $study, 'subject' => $subject];
                }
            }
        }
      }
      return $found;
    } catch (\Exception $e) {
      return [];
    }
  }
}