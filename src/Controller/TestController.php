<?php

namespace App\Controller;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(EntityManagerInterface $entityManager): JsonResponse
    {
        $repository = $entityManager->getRepository(Course::class);

        $res = [];

        $all = $repository->findAll();
        foreach ($all as $item) {
            $res[] =  $item->getId();
        }
        return $this->json($res);
    }
}
