<?php

namespace App\Controller;

use App\Entity\ServiceItem;
use App\Repository\ServiceItemRepository;
use App\Service\CloudinaryUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;
use App\Entity\Stylist;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

#[Route('/admin/services')]
#[IsGranted('ROLE_ADMIN')]
class AdminServiceController extends AbstractController

{

    #[Route('/', name: 'admin_services_index')]
    public function index(ServiceItemRepository $serviceRepository): Response

    {

        return $this->render('admin/services/index.html.twig', [

            'services' => $serviceRepository->findAll(),

        ]);

    }


    #[Route('/new', name: 'admin_services_new')]
    public function new(Request $request, EntityManagerInterface $em, CloudinaryUploader $uploader): Response
    {
        $service = new ServiceItem();

        $form = $this->createFormBuilder($service)
            ->add('title', TextType::class, ['label' => 'Заглавие (напр. Haircut)'])
            ->add('description', TextareaType::class, ['label' => 'Описание'])
            ->add('price', TextType::class, ['label' => 'Цена (напр. 45)'])
            ->add('duration', TextType::class, ['label' => 'Времетраене текст (напр. 45 min)'])

            // 👇 НОВО: Поле за избор на Стилисти
            ->add('stylists', EntityType::class, [
                'class' => Stylist::class,     // Връзка към таблицата със стилисти
                'choice_label' => 'name',      // Какво да пише до квадратчето (името на стилиста)
                'multiple' => true,            // Може да избереш повече от един
                'expanded' => true,            // true = Checkboxes, false = Dropdown
                'label' => 'Стилисти, извършващи услугата',
                'by_reference' => false,       // ВАЖНО: Позволява на Symfony да запише връзката правилно
            ])

            ->add('iconClass', TextType::class, [
                'label' => 'Иконка (напр. fas fa-cut)',
                'required' => false,
                'data' => 'fas fa-cut'
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Снимка',
                'mapped' => false,
                'required' => true,
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
                    $imageUrl = $uploader->uploadImage($imageFile, 'services');

                    // Store the full Cloudinary URL in the database
                    $service->setImageFilename($imageUrl);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                    return $this->redirectToRoute('admin_services_new');
                }
            }

            // 👇 ВАЖНО: Автоматично изчисляване на минутите за календара
            // Взимаме числото от текста (напр. от "45 min" взимаме 45)
            $durationText = $service->getDuration();
            $minutes = (int) filter_var($durationText, FILTER_SANITIZE_NUMBER_INT);
            if ($minutes > 0) {
                $service->setDurationMinutes($minutes);
            } else {
                $service->setDurationMinutes(30); // По подразбиране
            }

            $em->persist($service);
            $em->flush();

            return $this->redirectToRoute('admin_services_index');
        }

        return $this->render('admin/services/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/{id}/delete', name: 'admin_services_delete', methods: ['POST'])]
    public function delete(Request $request, ServiceItem $service, EntityManagerInterface $em, CloudinaryUploader $uploader): Response
    {
        if ($this->isCsrfTokenValid('delete' . $service->getId(), $request->request->get('_token'))) {
            // Delete from Cloudinary if it's a Cloudinary URL
            $imageUrl = $service->getImageFilename();
            if ($imageUrl && str_starts_with($imageUrl, 'http')) {
                try {
                    $uploader->deleteImage($imageUrl);
                } catch (\Exception $e) {
                    // Log error but continue with deletion
                    $this->addFlash('warning', 'Service deleted but image cleanup failed.');
                }
            }

            $em->remove($service);
            $em->flush();

            $this->addFlash('success', 'Service deleted successfully.');
        }

        return $this->redirectToRoute('admin_services_index');
    }

}