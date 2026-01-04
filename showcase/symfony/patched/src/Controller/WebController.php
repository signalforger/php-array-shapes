<?php

namespace App\Controller;

use App\Repository\JobListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Web controller for the frontend.
 */
class WebController extends AbstractController
{
    public function __construct(
        private readonly JobListingRepository $jobRepository
    ) {}

    #[Route('/', name: 'home')]
    public function home(Request $request): Response
    {
        $qb = $this->jobRepository->createQueryBuilder('j');

        // Search filter
        if ($q = $request->query->get('q')) {
            $qb->andWhere('j.title LIKE :q OR j.companyName LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        // Remote filter
        if ($request->query->has('remote') && $request->query->get('remote')) {
            $qb->andWhere('j.remote = :remote')
               ->setParameter('remote', true);
        }

        // Job type filter
        if ($jobType = $request->query->get('job_type')) {
            $qb->andWhere('j.jobType = :jobType')
               ->setParameter('jobType', $jobType);
        }

        // Ordering
        $qb->orderBy('j.postedAt', 'DESC')
           ->addOrderBy('j.createdAt', 'DESC');

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;

        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $jobs = $qb->getQuery()->getResult();
        $total = $this->jobRepository->count([]);

        return $this->render('home.html.twig', [
            'jobs' => $jobs,
            'stats' => $this->jobRepository->getStats(),
            'filters' => [
                'q' => $request->query->get('q'),
                'remote' => $request->query->get('remote'),
                'job_type' => $request->query->get('job_type'),
            ],
            'pagination' => [
                'page' => $page,
                'total' => $total,
                'pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    #[Route('/job/{id}', name: 'job_show')]
    public function show(int $id): Response
    {
        $job = $this->jobRepository->find($id);

        if (!$job) {
            throw $this->createNotFoundException('Job not found');
        }

        return $this->render('job.html.twig', [
            'job' => $job,
        ]);
    }

    #[Route('/api-docs', name: 'api_docs')]
    public function apiDocs(): Response
    {
        return $this->render('api-docs.html.twig');
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('about.html.twig');
    }
}
