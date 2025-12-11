# Tentamenbank

A Drupal module that provides a searchable interface for exam papers stored in an AWS S3 bucket. It integrates with DataNose to automatically highlight a student's currently enrolled courses based on their UvA student number.

## Prerequisites

### 1\. AWS S3 Bucket Structure

This module expects a specific folder structure in your S3 bucket to function correctly. All files must be located inside a root folder named `tentamenbank`.

**Structure:**

```text
/tentamenbank
    ├── /Study Name
    │     ├── /Course Name
    │     │     ├── 2023-01-01_Exam_Resit.pdf
    │     │     ├── 2023-01-01_Answers_Resit.pdf
```

### 2\. Configuration (`settings.php`)

Add your AWS S3 credentials to your site's `settings.php` file (usually located at `web/sites/default/settings.php`).

**settings.php**

```php
$settings['aws_s3'] = [
  'key'    => 'YOUR_AWS_KEY',
  'secret' => 'YOUR_AWS_SECRET',
  'region' => 'YOUR_AWS_REGION',
  'bucket' => 'YOUR_AWS_BUCKET',
];
```

## Installation

### 1\. Download the Module

Navigate to your Drupal custom modules directory and clone the repository.

```bash
cd ~/stack/drupal/web/modules/custom
git clone https://github.com/YOUR_USERNAME/tentamenbank.git
```

### 2\. Install Dependencies

Ensure the AWS SDK is available in your Drupal installation (if not already present).

```bash
composer require aws/aws-sdk-php
```

### 3\. Enable the Module

Enable the module via Drush or the Admin interface.

```bash
drush en tentamenbank -y
drush cr
```

## Updating

To update the module to the latest version:

```bash
git -C ~/stack/drupal/web/modules/custom/tentamenbank pull
cd ~/stack/drupal && drush cr
```

## How It Works

1.  **S3 Fetching:** The module uses the credentials from `settings.php` to list all folders inside the `/tentamenbank` directory of your S3 bucket.
2.  **Course Mapping:** It attempts to map folder names to Course IDs using a JSON file located at `public://tentamenbank_course_ids.json`.
3.  **Enrolment Check:**
      * It checks the current user's profile for the `field_uva_studentnummer`.
      * It queries the **DataNose API** (`https://api.datanose.nl/Enrolments/{student_id}`) to retrieve the user's active course enrollments.
      * Courses in the S3 bucket that match the user's enrolled Course IDs are displayed in a special **"My Enrolled Courses"** table at the top of the page.
4.  **Filtering:** Users can filter the main list by Study (Bachelor/Pre-master/Master) or search by Course Name/ID.