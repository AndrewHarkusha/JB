<?php

# src/App/JoboardBundle/Form/JobType.php

namespace App\JoboardBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use App\JoboardBundle\Entity\Job;


class JobType extends AbstractType
{
        /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type', 'choice', [
                'choices'  => Job::getTypes(),
                'expanded' => true
            ])
            ->add('company')
            ->add('logo', null, ['label' => 'Лого компании'])
            ->add('url')
            ->add('position')
            ->add('location')
            ->add('description')
            ->add('how_to_apply', null, ['label' => 'Как соискатель может подать свое резюме?'])
            ->add('token')
            ->add('is_public', null, ['label' => 'Публичная?'])
            ->add('email')
            ->add('category')
        ;
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'App\JoboardBundle\Entity\Job'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'app_joboardbundle_job';
    }
}
