<?php
// file generated with AI assistance: Claude Code - 2025-11-02

declare(strict_types=1);

namespace Dmstr\ApiConfiguration\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for ApiConfiguration config validation
 *
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ApiConfigurationConstraint extends Constraint
{
    public string $message = 'Invalid API configuration: {{ error }}';

    public function validatedBy(): string
    {
        return ApiConfigurationValidator::class;
    }
}
