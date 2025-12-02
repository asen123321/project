<?php

namespace App\Controller;

use App\Repository\GalleryImageRepository; // <--- ВАЖНО: Добавяме това, за да ползваме базата
use App\Repository\ServiceItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        GalleryImageRepository $galleryRepository,
        ServiceItemRepository $serviceRepository // <--- Добавете това
    ): Response
    {
        return $this->render('home/index.html.twig', [
            'galleryImages' => $galleryRepository->findBy([], ['createdAt' => 'DESC']),
            'services' => $serviceRepository->findAll(), // <--- Подаваме услугите
        ]);
    }
}