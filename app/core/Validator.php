<?php
/**
 * Validador de datos de formulario
 */

class Validator
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** Valida que el campo sea obligatorio */
    public function required(string $field, string $label): static
    {
        $val = trim($this->data[$field] ?? '');
        if ($val === '' || $val === null) {
            $this->errors[$field] = "$label es obligatorio.";
        }
        return $this;
    }

    /** Valida longitud máxima */
    public function maxLen(string $field, int $max, string $label): static
    {
        $val = $this->data[$field] ?? '';
        if (mb_strlen($val) > $max) {
            $this->errors[$field] = "$label no puede superar $max caracteres.";
        }
        return $this;
    }

    /** Valida que sea entero positivo */
    public function integer(string $field, string $label, int $min = 1, int $max = PHP_INT_MAX): static
    {
        $val = $this->data[$field] ?? '';
        if (!ctype_digit((string)$val) || (int)$val < $min || (int)$val > $max) {
            $this->errors[$field] = "$label debe ser un número entero entre $min y $max.";
        }
        return $this;
    }

    /** Valida formato de fecha YYYY-MM-DD */
    public function date(string $field, string $label): static
    {
        $val = $this->data[$field] ?? '';
        if ($val === '') return $this;
        $d = DateTime::createFromFormat('Y-m-d', $val);
        if (!$d || $d->format('Y-m-d') !== $val) {
            $this->errors[$field] = "$label tiene un formato de fecha inválido.";
        }
        return $this;
    }

    /** Valida formato de email */
    public function email(string $field, string $label): static
    {
        $val = $this->data[$field] ?? '';
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$label no tiene un formato de correo válido.";
        }
        return $this;
    }

    /**
     * Valida complejidad mínima de contraseña:
     *   - mínimo 8 caracteres
     *   - al menos una mayúscula
     *   - al menos una minúscula
     *   - al menos un dígito
     *
     * Si $allowEmpty es true y el campo viene vacío se omite (útil para
     * edición de usuario donde la contraseña es opcional).
     */
    public function passwordComplex(string $field, string $label, bool $allowEmpty = false): static
    {
        $val = (string)($this->data[$field] ?? '');
        if ($allowEmpty && trim($val) === '') {
            return $this;
        }
        if (mb_strlen($val) < 8) {
            $this->errors[$field] = "$label debe tener al menos 8 caracteres.";
            return $this;
        }
        if (!preg_match('/[A-Z]/', $val)) {
            $this->errors[$field] = "$label debe incluir al menos una letra mayúscula.";
            return $this;
        }
        if (!preg_match('/[a-z]/', $val)) {
            $this->errors[$field] = "$label debe incluir al menos una letra minúscula.";
            return $this;
        }
        if (!preg_match('/[0-9]/', $val)) {
            $this->errors[$field] = "$label debe incluir al menos un dígito.";
            return $this;
        }
        return $this;
    }

    /** Valida que el campo esté en un set de valores permitidos */
    public function inList(string $field, array $allowed, string $label): static
    {
        $val = $this->data[$field] ?? '';
        if ($val !== '' && !in_array($val, $allowed, true)) {
            $this->errors[$field] = "$label tiene un valor no permitido.";
        }
        return $this;
    }

    /** Agrega un error manualmente */
    public function addError(string $field, string $msg): static
    {
        $this->errors[$field] = $msg;
        return $this;
    }

    /** Retorna true si no hay errores */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /** Retorna true si hay errores */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** Obtiene todos los errores */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Obtiene el primer error de un campo */
    public function error(string $field): string
    {
        return $this->errors[$field] ?? '';
    }

    /** Obtiene el valor limpio de un campo */
    public function get(string $field, mixed $default = ''): mixed
    {
        return trim($this->data[$field] ?? $default);
    }
}
