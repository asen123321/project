<?php

namespace App\Controller;

use App\Entity\Stylist;
use App\Entity\ServiceItem;
use App\Repository\StylistRepository;
use App\Service\CloudinaryUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;

#[Route('/admin/stylists')]
#[IsGranted('ROLE_ADMIN')]
class AdminStylistController extends AbstractController
{
    #[Route('/', name: 'admin_stylists_index')]
    public function index(StylistRepository $stylistRepository): Response
    {
        return $this->render('admin/stylist/index.html.twig', [
            'stylists' => $stylistRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_stylists_new')]
    public function new(Request $request, EntityManagerInterface $em, CloudinaryUploader $uploader): Response
    {
        $stylist = new Stylist();

        $form = $this->createFormBuilder($stylist)
            ->add('name', TextType::class, [
                'label' => 'Име на стилиста'
            ])
            ->add('specialization', TextType::class, [
                'label' => 'Титла (напр. Senior Stylist)',
                'required' => false
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Биография',
                'required' => false
            ])

            // 👇 ТУК Е МАГИЯТА ЗА СВЪРЗВАНЕТО
            ->add('services', EntityType::class, [
                'class' => ServiceItem::class, // Избираме от таблицата ServiceItem
                'choice_label' => 'title',     // Какво да пише до квадратчето (името на услугата)
                'multiple' => true,            // Позволява избор на много услуги
                'expanded' => true,            // true = Checkboxes (списък), false = Dropdown
                'label' => 'Извършвани услуги',
                'by_reference' => false,       // Задължително за Many-to-Many
            ])

            ->add('imageFile', FileType::class, [
                'label' => 'Снимка',
                'mapped' => false, // Не се записва директно в базата, ние го обработваме ръчно
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Моля качете валидна снимка',
                    ])
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            // Upload to Cloudinary instead of local storage
            if ($imageFile) {
                try {
                    // Upload to Cloudinary and get the secure URL
                    $imageUrl = $uploader->uploadImage($imageFile, 'stylists');

                    // Store the full Cloudinary URL in the database
                    $stylist->setPhotoUrl($imageUrl);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                    return $this->redirectToRoute('admin_stylists_new');
                }
            }

            $em->persist($stylist);
            $em->flush();

            $this->addFlash('success', 'Стилистът беше добавен успешно!');

            return $this->redirectToRoute('admin_stylists_index');
        }

        return $this->render('admin/stylist/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}