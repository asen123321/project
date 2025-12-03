<?php

namespace App\Controller;

use App\Entity\GalleryImage;
use App\Repository\GalleryImageRepository;
use App\Service\CloudinaryUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;

#[Route('/admin/gallery')]
#[IsGranted('ROLE_ADMIN')]
class AdminGalleryController extends AbstractController
{
    #[Route('/', name: 'admin_gallery_index')]
    public function index(GalleryImageRepository $galleryRepository): Response
    {
        return $this->render('admin/gallery/index.html.twig', [
            'images' => $galleryRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_gallery_new')]
    public function new(Request $request, EntityManagerInterface $em, CloudinaryUploader $uploader): Response
    {
        $image = new GalleryImage();
        // Създайте формата директно тук или в отделен клас
        $form = $this->createFormBuilder($image)
            ->add('title', TextType::class, ['label' => 'Заглавие'])
            ->add('category', ChoiceType::class, [
                'choices' => [
                    'Подстригване' => 'haircut',
                    'Боядисване' => 'coloring',
                    'Прически' => 'styling',
                ],
                'label' => 'Категория'
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Снимка (JPG/PNG)',
                'mapped' => false, // Не е директно свързано с базата
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Моля качете валидна снимка',
                    ])
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                try {
                    // Upload to Cloudinary and get the URL
                    $imageUrl = $uploader->uploadImage($imageFile, 'gallery');

                    // Store the full Cloudinary URL in the database
                    $image->setFilename($imageUrl);
                    $image->setCreatedAt(new \DateTimeImmutable());

                    $em->persist($image);
                    $em->flush();

                    $this->addFlash('success', 'Image uploaded successfully!');

                    return $this->redirectToRoute('admin_gallery_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/gallery/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_gallery_delete', methods: ['POST'])]
    public function delete(Request $request, GalleryImage $image, EntityManagerInterface $em, CloudinaryUploader $uploader): Response
    {
        if ($this->isCsrfTokenValid('delete'.$image->getId(), $request->request->get('_token'))) {
            // Delete image from Cloudinary if it's a Cloudinary URL
            $filename = $image->getFilename();
            if (str_starts_with($filename, 'https://res.cloudinary.com/')) {
                try {
                    $uploader->deleteImage($filename);
                } catch (\Exception $e) {
                    // Log error but continue with database deletion
                }
            } else {
                // Fallback: Delete from local filesystem (for old images)
                $filePath = $this->getParameter('gallery_directory').'/'.$filename;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $em->remove($image);
            $em->flush();

            $this->addFlash('success', 'Image deleted successfully!');
        }

        return $this->redirectToRoute('admin_gallery_index');
    }
}
