<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use App\Utils\Base64FileExtractor;
use App\Utils\UploadedBase64File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use OpenApi\Annotations as OA;

class NotebookController extends AbstractController
{
    /**
     * @Route("/notebook", methods={"GET"})
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Limit of contacts",
     *     @OA\Schema(type="string")
     * )
     * @OA\Parameter(
     *     name="offset",
     *     in="query",
     *     description="Offset for paging",
     *     @OA\Schema(type="string")
     * )
     */
    public function list(Request $request, UrlHelper $urlHelper): JsonResponse
    {
        /** @var ContactRepository $repository */
        $repository = $this
            ->getDoctrine()
            ->getRepository(Contact::class);

        $contacts = $repository->getContactList(
            $request->get('limit', 10),
            $request->get('offset', 0)
        );

        foreach ($contacts as $contact) {
            $path = $urlHelper->getAbsoluteUrl(
                '/uploads/contacts/' . $contact->getPhotoFilename()
            );
            $contact->setPhotoFilename($path);
        }

        return new JsonResponse(['contacts' => $contacts]);
    }

    /**
     * @Route("/notebook/{id}", methods={"GET"})
     */
    public function show(int $id, UrlHelper $urlHelper): JsonResponse
    {
        $repository = $this
            ->getDoctrine()
            ->getRepository(Contact::class);

        $contact = $repository->findOneBy(['id' => $id]);
        if (!$contact) {
            throw $this->createNotFoundException('Contact not found');
        }

        $path = $urlHelper->getAbsoluteUrl(
            '/uploads/contacts/' . $contact->getPhotoFilename()
        );
        $contact->setPhotoFilename($path);

        return new JsonResponse(['contact' => $contact]);
    }

    /**
     * @Route("/notebook", methods={"POST"})
     *
     * @OA\Post(
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="company", type="string"),
     *                 @OA\Property(property="phoneNumber", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(
     *                     property="birth",
     *                     type="object",
     *                     @OA\Property(property="month", type="string"),
     *                     @OA\Property(property="day", type="string"),
     *                     @OA\Property(property="year", type="string"),
     *                 ),
     *                 @OA\Property(
     *                     property="photo",
     *                     type="object",
     *                     @OA\Property(property="originalName", type="string"),
     *                     @OA\Property(property="base64", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function create(Request $request, Base64FileExtractor $base64FileExtractor): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->submit($data);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new JsonResponse($form->getErrors(), Response::HTTP_BAD_REQUEST);
        }

        if (!empty($data['photo']['originalName']) && !empty($data['photo']['base64'])) {
            $base64Image = $data['photo']['base64'];
            $base64Image = $base64FileExtractor->extractBase64String($base64Image);
            $photoFile = new UploadedBase64File($base64Image, $data['photo']['originalName']);

            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $newFilename = $originalFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

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
     *
     * @OA\Post(
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="company", type="string"),
     *                 @OA\Property(property="phoneNumber", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(
     *                     property="birth",
     *                     type="object",
     *                     @OA\Property(property="month", type="string"),
     *                     @OA\Property(property="day", type="string"),
     *                     @OA\Property(property="year", type="string"),
     *                 ),
     *                 @OA\Property(
     *                     property="photo",
     *                     type="object",
     *                     @OA\Property(property="originalName", type="string"),
     *                     @OA\Property(property="base64", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
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
