<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PrestaShopBundle\Controller\Admin\Sell\Catalog\Product;

use Exception;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetEditableCombinationsList;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\QueryResult\CombinationListForEditing;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Builder\FormBuilderInterface;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Handler\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Search\Filters\ProductCombinationFilters;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Form\Admin\Sell\Product\Combination\CombinationListType;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CombinationController extends FrameworkBundleAdminController
{
    /**
     * Options used for the number of combinations per page
     */
    private const COMBINATIONS_PAGINATION_OPTIONS = [ProductCombinationFilters::LIST_LIMIT, 20, 50, 100];

    /**
     * Renders combinations list prototype (which contains form inputs submittable by ajax)
     * It can only be embedded into another view (does not have a route)
     *
     * @return Response
     */
    public function listFormAction(): Response
    {
        return $this->render('@PrestaShop/Admin/Sell/Catalog/Product/Blocks/combinations.html.twig', [
            'combinationLimitChoices' => self::COMBINATIONS_PAGINATION_OPTIONS,
            'combinationsLimit' => ProductCombinationFilters::LIST_LIMIT,
            'combinationsForm' => $this->createForm(CombinationListType::class)->createView(),
            'combinationItemForm' => $this->getCombinationItemFormBuilder()->getForm()->createView(),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param int $productId
     * @param ProductCombinationFilters $combinationFilters
     *
     * @return JsonResponse
     */
    public function getListAction(int $productId, ProductCombinationFilters $combinationFilters): JsonResponse
    {
        $combinationsList = $this->getQueryBus()->handle(new GetEditableCombinationsList(
            $productId,
            $this->getContextLangId(),
            $combinationFilters->getLimit(),
            $combinationFilters->getOffset(),
            $combinationFilters->getOrderBy(),
            $combinationFilters->getOrderWay(),
            $combinationFilters->getFilters()
        ));

        return $this->json($this->formatListResponse($combinationsList));
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     *
     * @param int $combinationId
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function updateCombinationFromListingAction(int $combinationId, Request $request): JsonResponse
    {
        $form = $this->getCombinationItemFormBuilder()->getFormFor($combinationId, [], [
            'method' => Request::METHOD_PATCH,
        ]);
        $form->handleRequest($request);

        try {
            $result = $this->getCombinationItemFormHandler()->handleFor($combinationId, $form);

            if (!$result->isValid()) {
                return $this->json(['errors' => $this->getFormErrorsForJS($form)], Response::HTTP_BAD_REQUEST);
            }
        } catch (Exception $e) {
            return $this->json(
                ['errors' => [$this->getFallbackErrorMessage(get_class($e), $e->getCode(), $e->getMessage())]],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json([]);
    }

    /**
     * @param CombinationListForEditing $combinationListForEditing
     *
     * @return array<string, array<int, array<string,bool|int|string>>|int>
     */
    private function formatListResponse(CombinationListForEditing $combinationListForEditing): array
    {
        $data = [
            'combinations' => [],
            'total' => $combinationListForEditing->getTotalCombinationsCount(),
        ];
        foreach ($combinationListForEditing->getCombinations() as $combination) {
            $data['combinations'][] = [
                'id' => $combination->getCombinationId(),
                'isSelected' => false,
                'name' => $combination->getCombinationName(),
                'reference' => $combination->getReference(),
                //@todo: don't forget image path when implemented in the query
                'impactOnPrice' => (string) $combination->getImpactOnPrice(),
                'quantity' => $combination->getQuantity(),
                'isDefault' => $combination->isDefault(),
            ];
        }

        return $data;
    }

    /**
     * @return FormHandlerInterface
     */
    private function getCombinationItemFormHandler(): FormHandlerInterface
    {
        return $this->get('prestashop.core.form.identifiable_object.combination_item_form_handler');
    }

    /**
     * @return FormBuilderInterface
     */
    private function getCombinationItemFormBuilder(): FormBuilderInterface
    {
        return $this->get('prestashop.core.form.identifiable_object.builder.combination_item_form_builder');
    }
}
