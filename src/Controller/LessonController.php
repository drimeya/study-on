<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Exception\BillingUnavailableException;
use App\Form\LessonType;
use App\Repository\LessonRepository;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/lessons')]
final class LessonController extends AbstractController
{
    #[Route(name: 'app_lesson_index', methods: ['GET'])]
    public function index(LessonRepository $lessonRepository): Response
    {
        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new/{id}', name: 'app_lesson_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, int $id = null): Response
    {
        $lesson = new Lesson();

        if ($id) {
            $course = $entityManager->getRepository(Course::class)->find($id);
            $lesson->setCourse($course);
        }

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lesson);
            $entityManager->flush();

            if ($id) {
                return $this->redirectToRoute('app_course_show', ['code' => $course->getCode()], Response::HTTP_SEE_OTHER);
            } else {
                return $this->redirectToRoute('app_lesson_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('lesson/new.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lesson_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Lesson $lesson, LessonRepository $lessonRepository, BillingClient $billingClient, SessionInterface $session): Response
    {
        $course = $lesson->getCourse();

        try {
            $billingCourse = $billingClient->getCourse($course->getCode());

            // Если курс платный — проверяем наличие активной оплаты
            if ($billingCourse !== null && !$billingCourse->isFree()) {
                $token = $session->get('billing_token');

                if (!$token) {
                    $this->addFlash('error', 'Для просмотра платного курса необходимо войти в систему.');
                    return $this->redirectToRoute('app_course_show', ['code' => $course->getCode()]);
                }

                $transactions = $billingClient->getTransactions($token, [
                    'type' => 'payment',
                    'course_code' => $course->getCode(),
                    'skip_expired' => 1,
                ]);

                if (empty($transactions)) {
                    $this->addFlash('error', 'Этот курс необходимо оплатить для просмотра уроков.');
                    return $this->redirectToRoute('app_course_show', ['code' => $course->getCode()]);
                }
            }
        } catch (BillingUnavailableException) {
            return $this->render('lesson/show.html.twig', [
                'lesson' => $lesson,
                'nextLesson' => null,
                'billingError' => true,
            ]);
        }

        $nextLesson = $lessonRepository->findNextLesson($lesson);

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'nextLesson' => $nextLesson,
            'billingError' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lesson_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, Lesson $lesson, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_lesson_show', ['id' => $lesson->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lesson/edit.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lesson_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, Lesson $lesson, EntityManagerInterface $entityManager): Response
    {
        $course = $lesson->getCourse();

        if ($this->isCsrfTokenValid('delete'.$lesson->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($lesson);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_show', ['code' => $course->getCode()]);
    }
}
