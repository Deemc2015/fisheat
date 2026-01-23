<?php
namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Request;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Payment;
use Bitrix\Sale\Result;
use Bitrix\Main\Entity\EntityError;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class InnerBonusHandler extends PaySystem\BaseServiceHandler implements PaySystem\IRefund
{
	/**
	 * @param Payment                   $payment
	 * @param \Bitrix\Main\Request|null $request = null
	 * @return PaySystem\ServiceResult
	 */
	public function initiatePay(Payment $payment, Request $request = null)
	{
		$result = new PaySystem\ServiceResult();
		return $result;
	}

	/**
	 * @return array
	 */
	public function getCurrencyList()
	{
		return [];
	}

	/**
	 * @param Payment $payment
	 * @param int     $refundableSum
	 *
	 * @return PaySystem\ServiceResult
	 */
	public function refund(Payment $payment, $refundableSum)
	{
		$result = new PaySystem\ServiceResult();
		return $result;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * @param Payment $payment
	 *
	 * @return Result
	 */
	public function creditNoDemand(Payment $payment)
	{
		$result = new Result();
		/** @var \Bitrix\Sale\PaymentCollection $collection */
		$collection = $payment->getCollection();

		/** @var \Bitrix\Sale\Order $order */
		$order = $collection->getOrder();

		if ($this->isUserBudgetLock($order)) {
			$result->addError(new EntityError(Loc::getMessage('ORDER_PSH_BONUS_ERROR_USER_BUDGET_LOCK')));
			return $result;
		}
		return $result;
	}

	/**
	 * @param Payment $payment
	 *
	 * @return PaySystem\ServiceResult
	 */
	public function debitNoDemand(Payment $payment)
	{
		return $this->refund($payment, $payment->getSum());
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * @param Order $order
	 *
	 * @return bool
	 */
	private function isUserBudgetLock(Order $order)
	{
		return false;
	}
}
