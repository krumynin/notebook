<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use OpenApi\Annotations as OA;

class NotebookController extends AbstractController
{
    /**
     * @Route("/notebook", methods={"GET"})
     * )
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Limit of contacts",
     *     @OA\Schema(type="string")
     * )
     * )
     * @OA\Parameter(
     *     name="offset",
     *     in="query",
     *     description="Offset for paging",
     *     @OA\Schema(type="string")
     * )
     */
    public function list(Request $request): JsonResponse
    {
        /** @var ContactRepository $repository */
        $repository = $this
            ->getDoctrine()
            ->getRepository(Contact::class);

        $contacts = $repository->getContactList(
            $request->get('limit', 10),
            $request->get('offset', 0)
        );

        return new JsonResponse(['contacts' => $contacts]);
    }

    /**
     * @Route("/notebook/{id}", methods={"GET"})
     */
    public function show(int $id): JsonResponse
    {
        $repository = $this
            ->getDoctrine()
            ->getRepository(Contact::class);

        $contact = $repository->findOneBy(['id' => $id]);
        if (!$contact) {
            throw $this->createNotFoundException('Contact not found');
        }

        return new JsonResponse(['contact' => $contact]);
    }

    /**
     * @Route("/notebook", methods={"POST"})
     */
    public function create(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if (!$form->isValid()) {
            return new JsonResponse($form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        /** @var UploadedFile $photoFile */
        $photoFile = $form->get('photo')->getData();

        if ($photoFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move(
                    $this->getParameter('contacts_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $contact->setPhotoFilename($newFilename);
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($contact);
        $em->flush();

        return new JsonResponse();
    }

    /**
     * @Route("/notebook/{id}", methods={"POST"})
     */
    public function update(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $em = $this
            ->getDoctrine()
            ->getManager();
        $repository = $em->getRepository(Contact::class);

        $contact = $repository->findOneBy(['id' => $request->get('id')]);
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isValid()) {
            return new JsonResponse($form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        /** @var UploadedFile $photoFile */
        $photoFile = $form->get('photo')->getData();

        if ($photoFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move(
                    $this->getParameter('contacts_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $contact->setPhotoFilename($newFilename);
        }

        $em->persist($contact);
        $em->flush();

        return new JsonResponse();
    }

    /**
     * @Route("/notebook/{id}", methods={"DELETE"})
     */
    public function delete(int $id): JsonResponse
    {
        $em = $this
            ->getDoctrine()
            ->getManager();
        $repository = $em->getRepository(Contact::class);

        $contact = $repository->findOneBy(['id' => $id]);
        if (!$contact) {
            throw $this->createNotFoundException('Contact not found');
        }

        $em->remove($contact);
        $em->flush();

        return new JsonResponse();
    }
}
