<?php
// file generated with AI assistance: Claude Code - 2025-11-01 00:00:00

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Validator;

use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Opis\JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Custom validator for ApiConfiguration config field
 *
 * Validates config JSON against dynamically generated unified schema
 */
class ApiConfigurationValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SchemaRegistry $schemaRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ApiConfigurationConstraint) {
            throw new UnexpectedTypeException($constraint, ApiConfigurationConstraint::class);
        }

        if ($value === null || $value === []) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', 'Configuration cannot be empty')
                ->addViolation();
            return;
        }

        // Get unified schema from SchemaRegistry
        $unifiedSchema = $this->schemaRegistry->getUnifiedSchema();

        // Convert to stdClass for validation
        $data = json_decode(json_encode($value));
        $schema = json_decode(json_encode($unifiedSchema));

        // Log what we're validating
        $this->logger->debug('Validating API configuration', [
            'data' => $value,
            'has_anyOf' => isset($unifiedSchema['anyOf']),
            'anyOf_count' => isset($unifiedSchema['anyOf']) ? count($unifiedSchema['anyOf']) : 0,
            'data_keys' => array_keys($value)
        ]);

        // Validate using opis/json-schema
        $validator = new Validator();
        $result = $validator->validate($data, $schema);

        if (!$result->isValid()) {
            $error = $result->error();

            // Log detailed error information including sub-errors
            $subErrors = [];
            if ($error->subErrors()) {
                foreach ($error->subErrors() as $idx => $subError) {
                    $subErrors[$idx] = [
                        'keyword' => $subError->keyword(),
                        'path' => $this->formatErrorPath($subError->data()->path()),
                        'args' => $subError->args()
                    ];

                    // For anyOf errors, log the nested validation errors
                    if ($subError->subErrors()) {
                        $subErrors[$idx]['nested'] = [];
                        foreach ($subError->subErrors() as $nestedIdx => $nestedError) {
                            $subErrors[$idx]['nested'][$nestedIdx] = [
                                'keyword' => $nestedError->keyword(),
                                'path' => $this->formatErrorPath($nestedError->data()->path()),
                                'args' => $nestedError->args()
                            ];
                        }
                    }
                }
            }

            $this->logger->warning('API configuration validation failed', [
                'error_keyword' => $error->keyword(),
                'error_args' => $error->args(),
                'sub_errors' => $subErrors,
                'data' => $value
            ]);

            $errorMessage = $this->formatError($error);

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', $errorMessage)
                ->addViolation();
        } else {
            $this->logger->debug('API configuration validation passed');
        }
    }

    /**
     * Format validation error for user-friendly display
     */
    private function formatError(\Opis\JsonSchema\Errors\ValidationError $error): string
    {
        $messages = [];
        $fieldErrors = [];

        // For anyOf errors, extract specific field validation errors
        if ($error->keyword() === 'anyOf' && $error->subErrors()) {
            foreach ($error->subErrors() as $subError) {
                // Check for nested property errors (format, minLength, etc.)
                if ($subError->subErrors()) {
                    foreach ($subError->subErrors() as $nestedError) {
                        $field = $this->extractFieldName($nestedError->data()->path());
                        $errorMsg = $this->formatFieldError($nestedError);

                        if ($field && $errorMsg && !isset($fieldErrors[$field])) {
                            $fieldErrors[$field] = $errorMsg;
                        }
                    }
                }

                // Check for missing required fields
                if ($subError->keyword() === 'required' && $subError->args()) {
                    $args = $subError->args();
                    if (isset($args['missing'])) {
                        $missing = $args['missing'];
                        // Handle both single field (string) and multiple fields (array)
                        $fields = is_array($missing) ? $missing : [$missing];
                        foreach ($fields as $field) {
                            if (!isset($fieldErrors[$field])) {
                                $fieldErrors[$field] = 'This field is required';
                            }
                        }
                    }
                }
            }
        }

        // If we found specific field errors, format them nicely
        if (!empty($fieldErrors)) {
            foreach ($fieldErrors as $field => $errorMsg) {
                $messages[] = sprintf('%s: %s', $field, $errorMsg);
            }
            return implode('; ', $messages);
        }

        // Fallback to generic error message
        return 'Configuration validation failed. Please check all required fields.';
    }

    /**
     * Extract field name from JSON path
     */
    private function extractFieldName(mixed $path): ?string
    {
        if (is_array($path) && count($path) > 0) {
            return (string) $path[count($path) - 1];
        }
        return null;
    }

    /**
     * Format a specific field error into user-friendly message
     */
    private function formatFieldError(\Opis\JsonSchema\Errors\ValidationError $error): ?string
    {
        $keyword = $error->keyword();
        $args = $error->args();

        return match($keyword) {
            'format' => isset($args['format'])
                ? sprintf('Must be a valid %s', $args['format'])
                : 'Invalid format',
            'minLength' => isset($args['min'])
                ? sprintf('Must be at least %d characters', $args['min'])
                : 'Too short',
            'maxLength' => isset($args['max'])
                ? sprintf('Must be at most %d characters', $args['max'])
                : 'Too long',
            'pattern' => 'Invalid format',
            'minimum' => isset($args['min'])
                ? sprintf('Must be at least %s', $args['min'])
                : 'Value too small',
            'maximum' => isset($args['max'])
                ? sprintf('Must be at most %s', $args['max'])
                : 'Value too large',
            default => null
        };
    }

    /**
     * Format a value for error display (handle arrays, objects, etc.)
     */
    private function formatErrorValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_object($value)) {
            return get_class($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return (string) $value;
    }

    /**
     * Format a JSON path for error display
     */
    private function formatErrorPath(mixed $path): string
    {
        if (is_array($path)) {
            return '/' . implode('/', array_map(fn($p) => $this->formatErrorValue($p), $path));
        }
        return $this->formatErrorValue($path);
    }
}
