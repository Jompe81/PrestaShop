<?php
/**
 * Mollie       https://www.mollie.nl
 *
 * @author      Mollie B.V. <info@mollie.nl>
 * @copyright   Mollie B.V.
 * @license     https://github.com/mollie/PrestaShop/blob/master/LICENSE.md
 *
 * @see        https://github.com/mollie/PrestaShop
 * @codingStandardsIgnoreStart
 */

namespace Mollie\Tests\Unit\Handler\Order;

use Cart;
use Exception;
use Mollie\Action\CreateOrderPaymentFeeAction;
use Mollie\Action\UpdateOrderTotalsAction;
use Mollie\Api\Resources\Payment;
use Mollie\DTO\PaymentFeeData;
use Mollie\Exception\Code\ExceptionCode;
use Mollie\Exception\CouldNotCreateOrderPaymentFee;
use Mollie\Exception\CouldNotUpdateOrderTotals;
use Mollie\Exception\FailedToProvidePaymentFeeException;
use Mollie\Exception\OrderCreationException;
use Mollie\Handler\Exception\CouldNotHandleOrderPaymentFee;
use Mollie\Handler\Order\OrderPaymentFeeHandler;
use Mollie\Logger\LoggerInterface;
use Mollie\Provider\PaymentFeeProviderInterface;
use Mollie\Repository\CartRepositoryInterface;
use Mollie\Repository\OrderRepositoryInterface;
use Mollie\Service\PaymentMethodService;
use Mollie\Tests\Unit\BaseTestCase;
use MolPaymentMethod;
use Order;

class OrderPaymentFeeHandlerTest extends BaseTestCase
{
    /** @var UpdateOrderTotalsAction */
    private $updateOrderTotalsAction;

    /** @var PaymentMethodService */
    private $paymentMethodService;

    /** @var PaymentFeeProviderInterface */
    private $paymentFeeProvider;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var LoggerInterface */
    private $logger;

    /** @var CreateOrderPaymentFeeAction */
    private $createOrderPaymentFeeAction;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updateOrderTotalsAction = $this->mock(UpdateOrderTotalsAction::class);
        $this->paymentMethodService = $this->mock(PaymentMethodService::class);
        $this->paymentFeeProvider = $this->mock(PaymentFeeProviderInterface::class);
        $this->orderRepository = $this->mock(OrderRepositoryInterface::class);
        $this->logger = $this->mock(LoggerInterface::class);
        $this->createOrderPaymentFeeAction = $this->mock(CreateOrderPaymentFeeAction::class);
        $this->cartRepository = $this->mock(CartRepositoryInterface::class);
    }

    public function testItSuccessfullyHandlesOrderPaymentFee(): void
    {
        $order = $this->createMock(Order::class);
        $order->id_cart = 1;

        $this->orderRepository->expects($this->once())->method('findOneBy')->willReturn($order);

        $cart = $this->createMock(Cart::class);
        $cart->expects($this->exactly(2))->method('getOrderTotal')->willReturn(12.1);

        $this->cartRepository->expects($this->once())->method('findOneBy')->willReturn($cart);

        $molPaymentMethod = $this->createMock(MolPaymentMethod::class);

        $this->paymentMethodService->expects($this->once())->method('getPaymentMethod')->willReturn($molPaymentMethod);

        $paymentFeeData = $this->createMock(PaymentFeeData::class);
        $paymentFeeData->expects($this->exactly(2))->method('getPaymentFeeTaxIncl')->willReturn(12.1);
        $paymentFeeData->expects($this->exactly(2))->method('getPaymentFeeTaxExcl')->willReturn(10);

        $this->paymentFeeProvider->expects($this->once())->method('getPaymentFee')->willReturn($paymentFeeData);

        $this->createOrderPaymentFeeAction->expects($this->once())->method('run');

        $this->updateOrderTotalsAction->expects($this->once())->method('run');

        $orderPaymentFeeHandler = new OrderPaymentFeeHandler(
            $this->paymentMethodService,
            $this->paymentFeeProvider,
            $this->createOrderPaymentFeeAction,
            $this->updateOrderTotalsAction,
            $this->orderRepository,
            $this->cartRepository,
            $this->logger
        );

        /** @var Payment $apiPayment */
        $apiPayment = $this->createMock(Payment::class);

        $apiPayment->amount = (object) [
            'value' => 12.1,
        ];

        $orderPaymentFeeHandler->addOrderPaymentFee(1, $apiPayment);
    }

    public function testItUnsuccessfullyHandlesOrderPaymentFeeUnknownError(): void
    {
        $order = $this->createMock(Order::class);
        $order->id_cart = 1;

        $this->orderRepository->expects($this->once())->method('findOneBy')->willReturn($order);

        $cart = $this->createMock(Cart::class);
        $cart->expects($this->exactly(1))->method('getOrderTotal')->willThrowException(new Exception());

        $this->cartRepository->expects($this->once())->method('findOneBy')->willReturn($cart);

        $orderPaymentFeeHandler = new OrderPaymentFeeHandler(
            $this->paymentMethodService,
            $this->paymentFeeProvider,
            $this->createOrderPaymentFeeAction,
            $this->updateOrderTotalsAction,
            $this->orderRepository,
            $this->cartRepository,
            $this->logger
        );

        /** @var Payment $apiPayment */
        $apiPayment = $this->createMock(Payment::class);

        $this->expectException(CouldNotHandleOrderPaymentFee::class);
        $this->expectExceptionCode(ExceptionCode::INFRASTRUCTURE_UNKNOWN_ERROR);

        $orderPaymentFeeHandler->addOrderPaymentFee(1, $apiPayment);
    }

    public function testItUnsuccessfullyHandlesOrderPaymentFeeFailedToRetrievePaymentMethod(): void
    {
        $order = $this->createMock(Order::class);
        $order->id_cart = 1;

        $this->orderRepository->expects($this->once())->method('findOneBy')->willReturn($order);

        $cart = $this->createMock(Cart::class);
        $cart->expects($this->exactly(2))->method('getOrderTotal')->willReturn(12.1);

        $this->cartRepository->expects($this->once())->method('findOneBy')->willReturn($cart);

        $this->paymentMethodService->expects($this->once())->method('getPaymentMethod')->willThrowException(new OrderCreationException());

        $orderPaymentFeeHandler = new OrderPaymentFeeHandler(
            $this->paymentMethodService,
            $this->paymentFeeProvider,
            $this->createOrderPaymentFeeAction,
            $this->updateOrderTotalsAction,
            $this->orderRepository,
            $this->cartRepository,
            $this->logger
        );

        /** @var Payment $apiPayment */
        $apiPayment = $this->createMock(Payment::class);

        $this->expectException(CouldNotHandleOrderPaymentFee::class);
        $this->expectExceptionCode(ExceptionCode::ORDER_FAILED_TO_RETRIEVE_PAYMENT_METHOD);

        $orderPaymentFeeHandler->addOrderPaymentFee(1, $apiPayment);
    }

    public function testItUnsuccessfullyHandlesOrderPaymentFeeFailedToRetrievePaymentFee(): void
    {
        $order = $this->createMock(Order::class);
        $order->id_cart = 1;

        $this->orderRepository->expects($this->once())->method('findOneBy')->willReturn($order);

        $cart = $this->createMock(Cart::class);
        $cart->expects($this->exactly(2))->method('getOrderTotal')->willReturn(12.1);

        $this->cartRepository->expects($this->once())->method('findOneBy')->willReturn($cart);

        $molPaymentMethod = $this->createMock(MolPaymentMethod::class);

        $this->paymentMethodService->expects($this->once())->method('getPaymentMethod')->willReturn($molPaymentMethod);

        $this->paymentFeeProvider->expects($this->once())->method('getPaymentFee')->willThrowException(new FailedToProvidePaymentFeeException());

        $orderPaymentFeeHandler = new OrderPaymentFeeHandler(
            $this->paymentMethodService,
            $this->paymentFeeProvider,
            $this->createOrderPaymentFeeAction,
            $this->updateOrderTotalsAction,
            $this->orderRepository,
            $this->cartRepository,
            $this->logger
        );

        /** @var Payment $apiPayment */
        $apiPayment = $this->createMock(Payment::class);

        $this->expectException(CouldNotHandleOrderPaymentFee::class);
        $this->expectExceptionCode(ExceptionCode::ORDER_FAILED_TO_RETRIEVE_PAYMENT_FEE);

        $orderPaymentFeeHandler->addOrderPaymentFee(1, $apiPayment);
    }

    public function testItUnsuccessfullyHandlesOrderPaymentFeeFailedToCreateOrderPaymentFee(): void
    {
        $order = $this->createMock(Order::class);
        $order->id_cart = 1;

        $this->orderRepository->expects($this->once())->method('findOneBy')->willReturn($order);

        $cart = $this->createMock(Cart::class);
        $cart->expects($this->exactly(2))->method('getOrderTotal')->willReturn(12.1);

        $this->cartRepository->expects($this->once())->method('findOneBy')->willReturn($cart);

        $molPaymentMethod = $this->createMock(MolPaymentMethod::class);

        $this->paymentMethodService->expects($this->once())->method('getPaymentMethod')->willReturn($molPaymentMethod);

        $paymentFeeData = $this->createMock(PaymentFeeData::class);
        $paymentFeeData->expects($this->exactly(1))->method('getPaymentFeeTaxIncl')->willReturn(12.1);
        $paymentFeeData->expects($this->exactly(1))->method('getPaymentFeeTaxExcl')->willReturn(10);

        $this->paymentFeeProvider->expects($this->once())->method('getPaymentFee')->willReturn($paymentFeeData);

        $this->createOrderPaymentFeeAction->expects($this->once())->method('run')->willThrowException(new CouldNotCreateOrderPaymentFee());

        $orderPaymentFeeHandler = new OrderPaymentFeeHandler(
            $this->paymentMethodService,
            $this->paymentFeeProvider,
            $this->createOrderPaymentFeeAction,
            $this->updateOrderTotalsAction,
            $this->orderRepository,
            $this->cartRepository,
            $this->logger
        );

        /** @var Payment $apiPayment */
        $apiPayment = $this->createMock(Payment::class);

        $this->expectException(CouldNotHandleOrderPaymentFee::class);
        $this->expectExceptionCode(ExceptionCode::ORDER_FAILED_TO_CREATE_ORDER_PAYMENT_FEE);

        $orderPaymentFeeHandler->addOrderPaymentFee(1, $apiPayment);
    }

    public function testItUnsuccessfullyHandlesOrderPaymentFeeFailedToUpdateOrderTotalWithPaymentFee(): void
    {
        $order = $this->createMock(Order::class);
        $order->id_cart = 1;

        $this->orderRepository->expects($this->once())->method('findOneBy')->willReturn($order);

        $cart = $this->createMock(Cart::class);
        $cart->expects($this->exactly(2))->method('getOrderTotal')->willReturn(12.1);

        $this->cartRepository->expects($this->once())->method('findOneBy')->willReturn($cart);

        $molPaymentMethod = $this->createMock(MolPaymentMethod::class);

        $this->paymentMethodService->expects($this->once())->method('getPaymentMethod')->willReturn($molPaymentMethod);

        $paymentFeeData = $this->createMock(PaymentFeeData::class);
        $paymentFeeData->expects($this->exactly(2))->method('getPaymentFeeTaxIncl')->willReturn(12.1);
        $paymentFeeData->expects($this->exactly(2))->method('getPaymentFeeTaxExcl')->willReturn(10);

        $this->paymentFeeProvider->expects($this->once())->method('getPaymentFee')->willReturn($paymentFeeData);

        $this->createOrderPaymentFeeAction->expects($this->once())->method('run');

        $this->updateOrderTotalsAction->expects($this->once())->method('run')->willThrowException(new CouldNotUpdateOrderTotals());

        $orderPaymentFeeHandler = new OrderPaymentFeeHandler(
            $this->paymentMethodService,
            $this->paymentFeeProvider,
            $this->createOrderPaymentFeeAction,
            $this->updateOrderTotalsAction,
            $this->orderRepository,
            $this->cartRepository,
            $this->logger
        );

        /** @var Payment $apiPayment */
        $apiPayment = $this->createMock(Payment::class);

        $apiPayment->amount = (object) [
            'value' => 12.1,
        ];

        $this->expectException(CouldNotHandleOrderPaymentFee::class);
        $this->expectExceptionCode(ExceptionCode::ORDER_FAILED_TO_UPDATE_ORDER_TOTAL_WITH_PAYMENT_FEE);

        $orderPaymentFeeHandler->addOrderPaymentFee(1, $apiPayment);
    }
}
