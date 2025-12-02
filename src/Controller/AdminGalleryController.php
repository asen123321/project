<?php

namespace App\Controller;

use App\Entity\GalleryImage;
use App\Repository\GalleryImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
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
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
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
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('gallery_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception
                }

                $image->setFilename($newFilename);
                $image->setCreatedAt(new \DateTimeImmutable());

                $em->persist($image);
                $em->flush();

                return $this->redirectToRoute('admin_gallery_index');
            }
        }

        return $this->render('admin/gallery/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_gallery_delete', methods: ['POST'])]
    public function delete(Request $request, GalleryImage $image, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$image->getId(), $request->request->get('_token'))) {
            // Изтриване на файла от папката
            $filePath = $this->getParameter('gallery_directory').'/'.$image->getFilename();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $em->remove($image);
            $em->flush();
        }

        return $this->redirectToRoute('admin_gallery_index');
    }
}
