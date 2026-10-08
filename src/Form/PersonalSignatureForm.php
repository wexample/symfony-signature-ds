<?php

namespace Wexample\SymfonySignatureDs\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyMediaDs\Form\MediaPickerType;

/**
 * The image one signs with, picked in one's media library — where it is
 * uploaded, and cropped.
 */
class PersonalSignatureForm extends AbstractForm
{
    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add('image', MediaPickerType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            'constraints' => [new NotNull()],
        ]);

        $this->builderAddSubmit($builder);
    }
}
