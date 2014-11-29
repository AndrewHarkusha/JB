<?php

# src/App/JoboardBundle/DataFixtures/ORM/LoadJobData

namespace App\JoboardBundle\DataFixtures\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use App\JoboardBundle\Entity\Job;

class LoadJobData extends AbstractFixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $em)
    {
        $jobExpired = new Job();
        $jobExpired->setCategory($em->merge($this->getReference('category-programming')));
        $jobExpired->setType('full-time');
        $jobExpired->setCompany('DevAcademy');
        $jobExpired->setLogo('logo.gif');
        $jobExpired->setUrl('http://www.devacademy.ru/');
        $jobExpired->setPosition('Web Developer Expired');
        $jobExpired->setLocation('Moscow, Rissia');
        $jobExpired->setDescription('Lorem ipsum dolor sit amet, consectetur adipisicing elit.');
        $jobExpired->setHowToApply('Send your resume to lorem.ipsum [at] dolor.sit');
        $jobExpired->setIsPublic(true);
        $jobExpired->setIsActivated(true);
        $jobExpired->setToken('job_expired');
        $jobExpired->setEmail('job@example.com');
        $jobExpired->setCreatedAt(new \DateTime('2005-12-01'));

        $jobFullTime = new Job();
        $jobFullTime->setCategory($em->merge($this->getReference('category-programming')));
        $jobFullTime->setType('full-time');
        $jobFullTime->setCompany('ООО Компания');
        $jobFullTime->setLogo('company_logo.png');
        $jobFullTime->setUrl('http://example.com/');
        $jobFullTime->setPosition('Web Разработчик');
        $jobFullTime->setLocation('Москва');
        $jobFullTime->setDescription('Нужен опытный PHP разработчик');
        $jobFullTime->setHowToApply('Высылайте резюме на resume@example.com');
        $jobFullTime->setIsPublic(true);
        $jobFullTime->setIsActivated(true);
        $jobFullTime->setToken('job_example_com');
        $jobFullTime->setEmail('resume@example.com');
        $jobFullTime->setExpiresAt(new \DateTime('+30 days'));



        $jobPartTime = new Job();
        $jobPartTime->setCategory($em->merge($this->getReference('category-design')));
        $jobPartTime->setType('part-time');
        $jobPartTime->setCompany('ООО Дизайн Компания');
        $jobPartTime->setLogo('design_company_logo.gif');
        $jobPartTime->setUrl('http://design.example.com/');
        $jobPartTime->setPosition('Web Дизайнер');
        $jobPartTime->setLocation('Москва');
        $jobPartTime->setDescription('Ищем профессионального дизайнера');
        $jobPartTime->setHowToApply('Высылайте резюме на designer_resume@example.com');
        $jobPartTime->setIsPublic(true);
        $jobPartTime->setIsActivated(true);
        $jobPartTime->setToken('designer_resume@example.com');
        $jobPartTime->setEmail('resume@example.com');
        $jobPartTime->setExpiresAt(new \DateTime('+30 days'));
        $em->persist($jobFullTime);
        $em->persist($jobPartTime);
        $em->persist($jobExpired);
        $em->flush();
    }

    public function getOrder()
    {
        return 2;
    }
}
