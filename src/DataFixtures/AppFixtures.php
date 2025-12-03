<?php

namespace App\DataFixtures;

use App\Entity\Service;
use App\Entity\Stylist;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create Admin User and Primary Stylist "Asen"
        $this->createAdminAndPrimaryStylist($manager);

        // Create Services
        $this->createServices($manager);

        $manager->flush();
    }

    private function createAdminAndPrimaryStylist(ObjectManager $manager): void
    {
        // Check if admin user already exists
        $adminEmail = 'asem4o@gmail.com';

        // Create or update admin user
        $admin = $manager->getRepository(User::class)->findOneBy(['email' => $adminEmail]);

        if (!$admin) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setUsername('asen_admin');
            $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

            // Set a default password - should be changed by admin
            $hashedPassword = $this->passwordHasher->hashPassword($admin, 'Admin123!');
            $admin->setPassword($hashedPassword);
            $admin->setFirstName('Asen');
            $admin->setLastName('Admin');

            $manager->persist($admin);
        }

        // Create primary stylist "Asen" - the only stylist in the system
        // Link to admin user account
        $stylist = new Stylist();
        $stylist->setName('Asen')
            ->setBio('Master stylist and salon owner with over 15 years of experience. Specializing in all hair services from classic cuts to advanced color techniques and styling.')
            ->setSpecialization('Master Stylist - All Services')
            ->setPhotoUrl('https://i.pravatar.cc/300?img=15')
            ->setIsActive(true)
            ->setUser($admin); // Link stylist to admin user

        $manager->persist($stylist);
    }

    private function createStylists(ObjectManager $manager): array
    {
        // DEPRECATED: This method is no longer used
        // We now only have one stylist: Asen (the admin)
        $stylistsData = [];

        $stylists = [];
        foreach ($stylistsData as $data) {
            $stylist = new Stylist();
            $stylist->setName($data['name'])
                ->setBio($data['bio'])
                ->setSpecialization($data['specialization'])
                ->setPhotoUrl($data['photoUrl'])
                ->setIsActive(true);

            $manager->persist($stylist);
            $stylists[] = $stylist;
        }

        return $stylists;
    }

    private function createServices(ObjectManager $manager): void
    {
        $servicesData = [
            // Basic Services
            [
                'name' => 'Women\'s Haircut',
                'description' => 'Professional wash, cut, and blow-dry. Includes consultation and styling.',
                'duration' => 45,
                'price' => '65.00',
            ],
            [
                'name' => 'Men\'s Haircut',
                'description' => 'Precision cut with hot towel treatment and styling. Beard trim available.',
                'duration' => 30,
                'price' => '45.00',
            ],
            [
                'name' => 'Children\'s Haircut (Under 12)',
                'description' => 'Kid-friendly haircut in a fun, comfortable environment.',
                'duration' => 25,
                'price' => '30.00',
            ],

            // Color Services
            [
                'name' => 'Single Process Color',
                'description' => 'All-over color application, gray coverage, or root touch-up.',
                'duration' => 90,
                'price' => '85.00',
            ],
            [
                'name' => 'Full Highlights',
                'description' => 'Full head highlights with toner. Includes cut and style.',
                'duration' => 180,
                'price' => '185.00',
            ],
            [
                'name' => 'Partial Highlights',
                'description' => 'Highlights around face and crown. Includes toner and style.',
                'duration' => 120,
                'price' => '125.00',
            ],
            [
                'name' => 'Balayage',
                'description' => 'Hand-painted highlights for natural, sun-kissed look. Includes toner.',
                'duration' => 150,
                'price' => '165.00',
            ],
            [
                'name' => 'Color Correction',
                'description' => 'Fix previous color mishaps. Price may vary based on complexity.',
                'duration' => 240,
                'price' => '250.00',
            ],

            // Specialty Services
            [
                'name' => 'Deep Conditioning Treatment',
                'description' => 'Intensive repair treatment for damaged hair. Keratin or Olaplex options.',
                'duration' => 30,
                'price' => '55.00',
            ],
            [
                'name' => 'Keratin Smoothing Treatment',
                'description' => 'Brazilian Blowout for frizz-free, smooth hair lasting 3-5 months.',
                'duration' => 180,
                'price' => '295.00',
            ],
            [
                'name' => 'Perm',
                'description' => 'Create beautiful waves or curls. Includes cut and style.',
                'duration' => 150,
                'price' => '135.00',
            ],

            // Styling Services
            [
                'name' => 'Blowout & Style',
                'description' => 'Professional wash and blow-dry with styling.',
                'duration' => 45,
                'price' => '55.00',
            ],
            [
                'name' => 'Formal Updo',
                'description' => 'Elegant updo for weddings, proms, or special events.',
                'duration' => 60,
                'price' => '95.00',
            ],
            [
                'name' => 'Bridal Hair Trial',
                'description' => 'Pre-wedding consultation and trial styling session.',
                'duration' => 90,
                'price' => '125.00',
            ],
            [
                'name' => 'Bridal Hair Styling',
                'description' => 'Wedding day hair styling. Includes trial session.',
                'duration' => 120,
                'price' => '185.00',
            ],

            // Extensions
            [
                'name' => 'Tape-In Extensions (Full Head)',
                'description' => 'Semi-permanent extensions lasting 6-8 weeks. Extensions not included.',
                'duration' => 120,
                'price' => '350.00',
            ],
            [
                'name' => 'Extension Removal',
                'description' => 'Safe removal of tape-in or bonded extensions.',
                'duration' => 60,
                'price' => '75.00',
            ],

            // Men's Services
            [
                'name' => 'Hot Shave',
                'description' => 'Traditional hot towel shave with premium products.',
                'duration' => 45,
                'price' => '50.00',
            ],
            [
                'name' => 'Beard Trim & Styling',
                'description' => 'Professional beard shaping and styling.',
                'duration' => 20,
                'price' => '25.00',
            ],
        ];

        foreach ($servicesData as $data) {
            $service = new Service();
            $service->setName($data['name'])
                ->setDescription($data['description'])
                ->setDurationMinutes($data['duration'])
                ->setPrice($data['price'])
                ->setIsActive(true);

            $manager->persist($service);
        }
    }
}
