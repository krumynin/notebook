<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class NotebookController extends AbstractController
{
    /**
     * @Route("/notebook/list", name="contact_list", methods={"GET"})
     */
    public function list(): Response
    {
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(Contact::class);

        $contacts = $repository->findAll();

        return $this->render('contact/list.html.twig', ['contacts' => $contacts]);
    }

    /**
     * @Route("/notebook/show/{id}", name="contact_show", methods={"GET"})
     */
    public function show(int $id): Response
    {
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(Contact::class);

        $contact = $repository->findOneBy(['id' => $id]);

        return $this->render('contact/show.html.twig', ['contact' => $contact]);
    }

    /**
     * @Route("/notebook/create", name="contact_create", methods={"GET","POST"})
     */
    public function create(Request $request, SluggerInterface $slugger): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
                    $form->addError(new FormError('File error upload'));

                    return $this->renderForm('contact/create.html.twig', [
                        'form' => $form,
                    ]);
                }

                $contact->setPhotoFilename($newFilename);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($contact);
            $em->flush();

            return $this->redirectToRoute('contact_list');
        }

        return $this->renderForm('contact/create.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @Route("/notebook/update/{id}", name="contact_update", methods={"GET","POST"})
     */
    public function update(Request $request, SluggerInterface $slugger): Response
    {
        $em = $this
            ->getDoctrine()
            ->getManager();
        $repository = $em->getRepository(Contact::class);

        $contact = $repository->findOneBy(['id' => $request->get('id')]);
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
                    $form->addError(new FormError('File error upload'));

                    return $this->renderForm('contact/update.html.twig', [
                        'form' => $form,
                    ]);
                }

                $contact->setPhotoFilename($newFilename);
            }

            $em->persist($contact);
            $em->flush();

            return $this->redirectToRoute('contact_show', ['id' => $contact->getId()]);
        }

        return $this->renderForm('contact/update.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @Route("/notebook/delete/{id}", name="contact_delete", methods={"DELETE"})
     */
    public function delete(int $id): Response
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

        return new Response('success');
    }
}
