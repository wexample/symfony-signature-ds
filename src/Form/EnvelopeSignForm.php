<?php

namespace Wexample\SymfonySignatureDs\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\IsTrue;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\FileInputType;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;

/**
 * Signing: the documents read and accepted, and the image signed with — the
 * signer's own signature when it has one, or an image uploaded here.
 */
class EnvelopeSignForm extends AbstractForm
{
    public const string OPTION_HAS_PERSONAL = 'has_personal_signature';

    public static bool $ajax = true;

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault(self::OPTION_HAS_PERSONAL, false);
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add('agree', SwitchInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            self::FIELD_OPTION_NAME_REQUIRED => true,
            'constraints' => [new IsTrue()],
        ]);

        if ($options[self::OPTION_HAS_PERSONAL]) {
            $builder->add('use_personal', SwitchInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                self::FIELD_OPTION_NAME_REQUIRED => false,
                'data' => true,
            ]);
        }

        $builder->add('image', FileInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            self::FIELD_OPTION_NAME_REQUIRED => false,
            'help' => true,
            'constraints' => [new Image(maxSize: '2M', mimeTypes: ['image/png', 'image/jpeg', 'image/webp'])],
        ]);

        $this->builderAddSubmit($builder);
    }
}
