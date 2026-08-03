<?php

declare(strict_types=1);

namespace ElearningVD;

defined('ABSPATH') || exit;

final class ProfileFields
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $fields;

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    /**
     * @param array<string, mixed> $field
     */
    public function add_field(string $key, array $field): void
    {
        $this->fields[$key] = $field;
    }

    public function remove_field(string $key): void
    {
        unset($this->fields[$key]);
    }

    /**
     * @param array<string, mixed> $field
     */
    public function replace_field(string $key, array $field): void
    {
        $this->fields[$key] = $field;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->fields;
    }
}
