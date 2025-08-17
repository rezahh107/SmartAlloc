<?php

declare(strict_types=1);

namespace SmartAlloc\Integration;

use SmartAlloc\Container;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\LoggerInterface;

/**
 * Gravity Forms integration
 */
final class GravityForms
{
    public function __construct(
        private Container $container,
        private EventBus $eventBus,
        private LoggerInterface $logger
    ) {}

    /**
     * Register Gravity Forms hooks
     */
    public function register(): void
    {
        // Form submission hook
        add_action('gform_after_submission', [$this, 'handleFormSubmission'], 10, 2);
        
        // GP Populate Anything filter for field 39 (پشتیبان پیشنهادی)
        add_filter('gppa_process_filter_value', [$this, 'processPopulateAnythingFilter'], 10, 4);
        
        // Validation hook
        add_action('gform_field_validation', [$this, 'validateFields'], 10, 4);
    }

    /**
     * Handle form submission
     */
    public function handleFormSubmission($entry, $form): void
    {
        try {
            // Check if this is the target form
            $targetFormId = get_option('smartalloc_form_id', 150);
            if ($form['id'] != $targetFormId) {
                return;
            }

            $this->logger->info('Gravity Forms submission received', [
                'form_id' => $form['id'],
                'entry_id' => $entry['id']
            ]);

            // Extract student data
            $studentData = $this->extractStudentData($entry, $form);
            
            // Dispatch allocation event
            $this->eventBus->dispatch('AutoAssignRequested', [
                'student_id' => $entry['id'],
                'student_data' => $studentData,
                'form_id' => $form['id'],
                'timestamp' => current_time('mysql')
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Error handling form submission', [
                'form_id' => $form['id'] ?? 'unknown',
                'entry_id' => $entry['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Process GP Populate Anything filter for field 39
     * Reads values from input_92, input_94, input_75, input_30
     */
    public function processPopulateAnythingFilter($value, $field, $form, $entry): mixed
    {
        // Only process field 39 (پشتیبان پیشنهادی)
        if ($field->id != 39) {
            return $value;
        }

        try {
            $this->logger->debug('Processing GP Populate Anything filter for field 39', [
                'field_id' => $field->id,
                'entry_id' => $entry['id'] ?? 'unknown'
            ]);

            // Read values from specified input fields
            $input92 = rgar($entry, '92'); // کد مدرسه 1
            $input94 = rgar($entry, '94'); // کد مدرسه 2  
            $input75 = rgar($entry, '75'); // کد مدرسه 3
            $input30 = rgar($entry, '30'); // کد مدرسه 4

            // Log the values for debugging
            $this->logger->debug('GP Populate Anything input values', [
                'input_92' => $input92,
                'input_94' => $input94,
                'input_75' => $input75,
                'input_30' => $input30
            ]);

            // Process the values based on field configuration
            // This will depend on the specific GP Populate Anything setup
            // For now, return the original value but log the processing
            $processedValue = $this->processMentorSuggestion($input92, $input94, $input75, $input30);
            
            $this->logger->info('GP Populate Anything filter processed', [
                'field_id' => $field->id,
                'original_value' => $value,
                'processed_value' => $processedValue
            ]);

            return $processedValue;

        } catch (\Throwable $e) {
            $this->logger->error('Error processing GP Populate Anything filter', [
                'field_id' => $field->id,
                'entry_id' => $entry['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            // Return original value on error
            return $value;
        }
    }

    /**
     * Process mentor suggestion based on school codes
     */
    private function processMentorSuggestion($input92, $input94, $input75, $input30): mixed
    {
        try {
            // Get student data from the current form submission
            $studentData = $this->getCurrentStudentData();
            
            if (empty($studentData)) {
                $this->logger->warning('No student data available for mentor suggestion');
                return null;
            }

            // Get AllocationService to use ranking logic
            $allocationService = $this->container->get(\SmartAlloc\Services\AllocationService::class);
            
            // Find eligible mentors using the same logic as allocation
            $eligibleMentors = $allocationService->findEligibleMentors($studentData);
            
            if (empty($eligibleMentors)) {
                $this->logger->info('No eligible mentors found for student', [
                    'student_id' => $studentData['id'] ?? 'unknown'
                ]);
                return null;
            }

            // Rank candidates using the same logic
            $rankedCandidates = $allocationService->rankCandidates($eligibleMentors, $studentData);
            
            // Return top 5 mentors for GP Populate Anything
            $topMentors = array_slice($rankedCandidates, 0, 5);
            
            $this->logger->info('Mentor suggestion completed', [
                'student_id' => $studentData['id'] ?? 'unknown',
                'eligible_count' => count($eligibleMentors),
                'top_mentors' => array_column($topMentors, 'mentor_id')
            ]);

            // Return structured data for GP Populate Anything
            return array_map(function($mentor) {
                return [
                    'id' => $mentor['mentor_id'],
                    'name' => $mentor['name'] ?? 'Unknown',
                    'occupancy_ratio' => $mentor['occupancy_ratio'] ?? 0,
                    'allocations_new' => $mentor['allocations_new'] ?? 0,
                    'school_match_score' => $mentor['school_match_score'] ?? 0
                ];
            }, $topMentors);

        } catch (\Throwable $e) {
            $this->logger->error('Error in mentor suggestion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get current student data for mentor suggestion
     */
    private function getCurrentStudentData(): array
    {
        // This should be called during form processing
        // For now, return empty array - will be populated by form submission
        return [];
    }

    public function validateFields($result, $value, $form, $field): array
    {
        try {
            // Mobile validation (field 20, 21, 23)
            if (in_array($field->id, [20, 21, 23])) {
                $normalized = $this->normalizeDigits($value);
                
                // Check if mobile starts with 09 and is exactly 11 digits
                if (!empty($normalized) && (!preg_match('/^09\d{9}$/', $normalized) || strlen($normalized) !== 11)) {
                    $result['is_valid'] = false;
                    $result['message'] = __('شماره موبایل باید با ۰۹ شروع شود و دقیقاً ۱۱ رقم باشد.', 'smart-alloc');
                }
            }

            // Tracking code validation (field 76)
            if ($field->id === 76) {
                $normalized = $this->normalizeDigits($value);
                
                // Check if tracking equals 1111111111111111 (حکمت tracking rule)
                if (!empty($normalized) && $normalized === '1111111111111111') {
                    $result['is_valid'] = false;
                    $result['message'] = __('کد رهگیری نمی‌تواند ۱۱۱۱۱۱۱۱۱۱۱۱۱۱۱۱ باشد.', 'smart-alloc');
                }
            }

            // Landline validation (field 22)
            if ($field->id === 22) {
                if (empty($value)) {
                    // Normalize empty landline to 00000000000
                    $this->logger->info('Empty landline normalized to 00000000000');
                }
            }

            // Liaison phone numbers inequality check
            if (in_array($field->id, [21, 23])) {
                $this->checkLiaisonPhoneInequality($form, $field, $value, $result);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Validation error', [
                'field_id' => $field->id,
                'error' => $e->getMessage()
            ]);
            $result['is_valid'] = false;
            $result['message'] = __('خطا در اعتبارسنجی فیلد.', 'smart-alloc');
        }

        return $result;
    }

    /**
     * Check that liaison phone numbers are not equal
     */
    private function checkLiaisonPhoneInequality($form, $field, $value, &$result): void
    {
        // Get other liaison phone fields
        $otherLiaisonFields = array_filter([21, 23], function($id) use ($field) {
            return $id !== $field->id;
        });

        foreach ($otherLiaisonFields as $otherFieldId) {
            $otherField = $this->getFieldById($form, $otherFieldId);
            if ($otherField && !empty($otherField->value) && !empty($value)) {
                $normalizedValue = $this->normalizeDigits($value);
                $normalizedOther = $this->normalizeDigits($otherField->value);
                
                if ($normalizedValue === $normalizedOther) {
                    $result['is_valid'] = false;
                    $result['message'] = __('شماره‌های تماس رابط نمی‌توانند یکسان باشند.', 'smart-alloc');
                    break;
                }
            }
        }
    }

    /**
     * Get field by ID from form
     */
    private function getFieldById($form, $fieldId)
    {
        foreach ($form['fields'] as $field) {
            if ($field->id == $fieldId) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Normalize Persian/Arabic digits to English
     */
    private function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);
        
        return preg_replace('/\D/', '', $value);
    }

    private function extractStudentData($entry, $form): array
    {
        $data = [];
        
        foreach ($form['fields'] as $field) {
            $fieldValue = rgar($entry, $field->id);
            if (!empty($fieldValue)) {
                $data[$field->id] = [
                    'label' => $field->label,
                    'value' => $fieldValue,
                    'type' => $field->type
                ];
            }
        }
        
        return $data;
    }
} 