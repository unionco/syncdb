<?php

namespace unionco\syncdb\Model;

abstract class ValidationModel
{
    /** @var string[] */
    protected $warnings = [];

    /** @var string[] */
    protected $errors = [];

    public function valid(): bool
    {
        return true;
    }


    /**
     * Get the value of warnings
     * @return string[]
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    public function getWarningsString(): string
    {
        return join(', ', $this->warnings);
    }

    /**
     * Set the value of warnings
     *
     * @return  self
     */
    public function setWarnings(array $warnings)
    {
        $this->warnings = $warnings;

        return $this;
    }

    /**
     * Get the value of errors
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function getErrorsString(): string
    {
        return join(', ', $this->errors);
    }


    /**
     * Set the value of errors
     *
     * @return  self
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;

        return $this;
    }
}
