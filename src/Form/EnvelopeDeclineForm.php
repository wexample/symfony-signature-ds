<?php

namespace Wexample\SymfonySignatureDs\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\TextareaInputType;

/**
 * Declining to sign, and why: the sender is told.
 */
class EnvelopeDeclineForm extends AbstractForm
{
    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add('reason', TextareaInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            self::FIELD_OPTION_NAME_REQUIRED => false,
        ]);

        $this->builderAddSubmit($builder);
    }
}
