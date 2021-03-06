<?php

namespace App\Controller\Admin;

use App\MatchFinder\MatchFinder;
use App\MatchFinder\ZipCode;
use App\Repository\HelperRepository;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/export")
 */
class ExportController extends AbstractController
{
    /**
     * @Route("/helpers", name="admin_export_helpers")
     */
    public function helpers(HelperRepository $repository): Response
    {
        $csv = $this->createCsvWriter();
        $csv->insertOne(['E-mail', 'Prénom', 'Nom']);

        $helpers = $repository->exportAll();
        foreach ($helpers as $helper) {
            $csv->insertOne([$helper['email'], $helper['firstName'], $helper['lastName']]);
        }

        return $this->createCsvResponse('helpers-'.date('Y-m-d-H-i').'.csv', $csv->getContent());
    }

    /**
     * @Route("/unmatched", name="admin_export_unmatched")
     */
    public function unmatched(MatchFinder $matchFinder): Response
    {
        $csv = $this->createCsvWriter();
        $csv->insertOne(['ID', 'Département', 'Prénom', 'Code postal', 'Besoin']);

        foreach ($matchFinder->findUnmatchedNeeds() as $matches) {
            foreach ($matches as $match) {
                if ($need = $match->getGroceriesNeed()) {
                    $csv->insertOne([
                        $need->getId(),
                        ZipCode::DEPARTMENTS[substr($need->zipCode, 0, 2)] ?? '',
                        $need->firstName,
                        str_pad($need->zipCode, 5, ' ', STR_PAD_LEFT),
                        'Courses',
                    ]);
                }

                foreach ($match->getBabysitNeeds() as $need) {
                    $csv->insertOne([
                        $need->getId(),
                        ZipCode::DEPARTMENTS[substr($need->zipCode, 0, 2)] ?? '',
                        $need->firstName,
                        str_pad($need->zipCode, 5, ' ', STR_PAD_LEFT),
                        'Garde d\'un enfant de '.$need->childAgeRange.' ans',
                    ]);
                }
            }
        }

        return $this->createCsvResponse('besoins-'.date('Y-m-d-H-i').'.csv', $csv->getContent());
    }

    private function createCsvWriter(): Writer
    {
        $csv = Writer::createFromString();
        $csv->setDelimiter(',');
        $csv->setOutputBOM(Writer::BOM_UTF8);

        return $csv;
    }

    private function createCsvResponse(string $name, string $content): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $name
        ));

        return $response;
    }
}
