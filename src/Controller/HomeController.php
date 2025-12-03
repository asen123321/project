<?php

namespace App\Controller;

use App\Repository\GalleryImageRepository;
use App\Repository\ServiceItemRepository;
use App\Repository\StylistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        GalleryImageRepository $galleryRepository,
        ServiceItemRepository $serviceRepository,
        StylistRepository $stylistRepository
    ): Response
    {
        return $this->render('home/index.html.twig', [
            'galleryImages' => $galleryRepository->findBy([], ['createdAt' => 'DESC']),
            'services' => $serviceRepository->findAll(),
            'stylists' => $stylistRepository->findBy(['isActive' => true], ['id' => 'ASC']),
        ]);
    }
}