<?php

/*
 *  Copyright (C) 2012 Platoniq y Fundación Fuentes Abiertas (see README for details)
 * 	This file is part of Goteo.
 *
 *  Goteo is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  Goteo is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with Goteo.  If not, see <http://www.gnu.org/licenses/agpl.txt>.
 *
 */

namespace Goteo\Library
{
    use Goteo\Model\Invest,
        Goteo\Model\Project,
        Goteo\Model\User,
        Goteo\Library\Feed,
        Goteo\Core\Redirection;

    require_once(GOTEO_PAYPAL_ADAPTIVE_PATH.'services/AdaptivePayments/AdaptivePaymentsService.php');
    require_once(GOTEO_PAYPAL_ADAPTIVE_PATH.'PPLoggingManager.php');
    require_once(GOTEO_PATH_LIBRARY.'paypal/stub.php');

    /*
     * Clase para usar los adaptive payments de paypal
     * Reference used: https://www.x.com/developers/paypal/documentation-tools/paypal-sdk-index
     */

    class Paypal
    {
        /*
         * To change the paypal settings modify the library/paypal_adaptive/sdk_config.ini file
         */
        static function generatePaypalSettings($invest) {
            if (PAYPAL_MODE == 'sandbox') {
                $paypal_settings = array(
                    'cancelUrl' => SITE_URL."/project/{$invest->project}/invest/?confirm=fail",
                    'returnUrl' => SITE_URL."/invest/confirmed/{$invest->project}/{$invest->id}",
                    'currencyCode' => 'USD',
                    'RequestEnvelopeCode' => 'en_US'
                );
            }
            return $paypal_settings;
        }

        /**
         * @param object invest instancia del aporte: id, usuario, proyecto, cuenta, cantidad
         *
         * Método para crear un preapproval para un aporte
         * va a mandar al usuario a paypal para que confirme
         *
         * @TODO poner límite máximo de dias a lo que falte para los 40/80 dias para evitar las cancelaciones
         */
        public static function preapproval($invest, &$errors = array()) {
            $paypal_settings = self::generatePaypalSettings($invest);

            $config = \PPConfigManager::getInstance();
            $redirect_url = $config->get('service.RedirectURL');

            $startingDate = date("Y-m-d");

            // create request
            $requestEnvelope = new \RequestEnvelope($paypal_settings['RequestEnvelopeCode']);
            $preapprovalRequest = new \PreapprovalRequest($requestEnvelope, $paypal_settings['cancelUrl'], $paypal_settings['currencyCode'], $paypal_settings['returnUrl'], $startingDate);

            //Optional
            //$preapprovalRequest->endingDate = $_POST['endingDate'];
            $preapprovalRequest->maxAmountPerPayment = $invest->amount;
            $preapprovalRequest->maxNumberOfPayments = 1;
            $preapprovalRequest->maxNumberOfPaymentsPerPeriod = 1;
            $preapprovalRequest->maxAmountPerPayment = $invest->amount;

            //Message to paypal user
            $preapprovalRequest->memo = 'preapproval';

            $service = new \AdaptivePaymentsService();
            try {
                $response = $service->Preapproval($preapprovalRequest);
            } catch(Exception $ex) {
                require_once 'Common/Error.php';
                exit;
            }

            $ack = strtoupper($response->responseEnvelope->ack);
            if ($ack != "SUCCESS") {
                /*echo "<b>Error </b>";
                echo "<pre>";
                print_r($response);
                echo "</pre>";*/
                error_log('preapproval:'.print_r($response, 1));
            } else {
/*
                echo "<pre>";
                print_r($response);
                echo "</pre>";
*/
                // Redirect to paypal.com here
                $token = $response->preapprovalKey;
                $payPalURL = $redirect_url.'_ap-preapproval&preapprovalkey='.$token;
                $invest->setPreapproval($token);
                return $payPalURL;
                /*
                echo "<table>";
                echo "<tr><td>Ack :</td><td><div id='Ack'>$ack</div> </td></tr>";
                echo "<tr><td>PreapprovalKey :</td><td><div id='PreapprovalKey'>$token</div> </td></tr>";
                echo "<tr><td><a href=$payPalURL><b>Redirect URL to Complete Preapproval Authorization</b></a></td></tr>";
                echo "</table>";*/
            }
        }

        /*
         *  Metodo para ejecutar pago (desde cron)
         * Recibe parametro del aporte (id, cuenta, cantidad)
         *
         * Es un pago encadenado, la comision del 8% a Goteo y el resto al proyecto
         *
         */
        public static function pay($invest, &$errors = array()) {
            $paypal_settings = self::generatePaypalSettings($invest);
            $requestEnvelope = new \RequestEnvelope($paypal_settings['RequestEnvelopeCode']);
            $logger = new \PPLoggingManager('Pay');

            $config = \PPConfigManager::getInstance();
            $paypal_username = $config->get('acct1.email');

            $i = 0;
            //The primary user (goteo paypal account) no commision for now

            $receiver[$i] = new \Receiver();
            $receiver[$i]->email = $paypal_username;
            $receiver[$i]->amount = $invest->amount;
            $receiver[$i]->primary = 'true';

            $i = 1;
            //The secondary user (founder of the proyect)
            $receiver[$i] = new \Receiver();
            $receiver[$i]->email = $invest->account;
            $receiver[$i]->amount = $invest->amount;
            $receiver[$i]->primary = 'false';

            /*if ($_POST['invoiceId'][$i] != "") {
                $receiver[$i]->invoiceId = $_POST['invoiceId'][$i];
            }*/

            /*if($_POST['paymentType'][$i] != "" && $_POST['paymentType'][$i] != DEFAULT_SELECT) {
            <option>GOODS</option>
            <option>SERVICE</option>
            <option>PERSONAL</option>
            <option>CASHADVANCE</option>
            <option>DIGITALGOODS</option>*/

            $receiver[$i]->paymentType = 'SERVICE';

            //}

            /*if($_POST['paymentSubType'][$i] != "") {
                $receiver[$i]->paymentSubType = $_POST['paymentSubType'][$i];
            }*/

            /*if($_POST['phoneCountry'][$i] != "" && $_POST['phoneNumber'][$i]) {
                $receiver[$i]->phone = new PhoneNumberType($_POST['phoneCountry'][$i], $_POST['phoneNumber'][$i]);
                if($_POST['phoneExtn'][$i] != "") {
                    $receiver[$i]->phone->extension = $_POST['phoneExtn'][$i];
                }
            }*/

            $receiverList = new \ReceiverList($receiver);
            $payRequest = new \PayRequest($requestEnvelope, 'PAY', $paypal_settings['cancelUrl'], $paypal_settings['currencyCode'], $receiverList, $paypal_settings['returnUrl']);

            $payRequest->preapprovalKey = $invest->preapproval;

            //$payRequest->sender = new \SenderIdentifier();
            //$payRequest->sender->email  = $paypal_username;

            $service = new \AdaptivePaymentsService();
            try {
                $response = $service->Pay($payRequest);
                if (strtoupper($response->paymentExecStatus) == 'COMPLETED') {
                    $invest->setPayment($response->payKey);
                    $invest->setStatus(1);
                    return true;
                }
                return false;
            } catch(Exception $ex) {
                require_once 'Common/Error.php';
            }
            $logger->log("Received payResponse:");
            return false;
        }

        /*
         *  Metodo para ejecutar pago secundario (desde cron/dopay)
         * Recibe parametro del aporte (id, cuenta, cantidad)
         */
        public static function doPay($invest, &$errors = array()) {
            self::pay($invest, $errors);
        }

        /**
         * Llamada a paypal para obtener los detalles de un preapproval
         */
        public static function preapprovalDetails($invest, &$errors = array()) {
            $paypal_settings = self::generatePaypalSettings($invest);
            $logger = new \PPLoggingManager('PreApproval');

            // create request
            $requestEnvelope = new \RequestEnvelope($paypal_settings['RequestEnvelopeCode']);
            $preapprovalDetailsRequest = new \PreapprovalDetailsRequest($requestEnvelope, $invest->preapproval);
            $logger->log("Created PreapprovalDetailsRequest Object");

            $service = new \AdaptivePaymentsService();
            try {
                $response = $service->PreapprovalDetails($preapprovalDetailsRequest);
                return $response;
            } catch(Exception $ex) {
                return false;
                require_once 'Common/Error.php';
                exit;
            }
        }

        /*
         * Llamada a paypal para obtener los detalles de un cargo
         */
        public static function paymentDetails($invest, &$errors = array()) {
            $paypal_settings = self::generatePaypalSettings($invest);
            $logger = new \PPLoggingManager('PaymentDetails');

            // create request
            $requestEnvelope = new \RequestEnvelope($paypal_settings['RequestEnvelopeCode']);
            $paymentDetailsReq = new \PaymentDetailsRequest($requestEnvelope);

            $paymentDetailsReq->payKey = $invest->payment;
            //$paymentDetailsReq->transactionId = $invest->transaction;
            //$paymentDetailsReq->trackingId = $_POST['trackingId'];
            $logger->log("Created paymentDetailsRequest Object");
            $service = new \AdaptivePaymentsService();
            try {
                $response = $service->PaymentDetails($paymentDetailsReq);
                return $response;
            } catch(Exception $ex) {
                return false;
                require_once 'Common/Error.php';
            }
        }

        /**
         * Llamada para cancelar un preapproval (si llega a los 40 sin conseguir el mínimo)
         * recibe la instancia del aporte
         */
        public static function cancelPreapproval($invest, &$errors = array()) {
            $paypal_settings = self::generatePaypalSettings($invest);

            $logger = new \PPLoggingManager('CancelPreapproval');

            // create request
            $requestEnvelope = new \RequestEnvelope($paypal_settings['RequestEnvelopeCode']);
            var_dump($invest->preapproval);
            $cancelPreapprovalReq = new \CancelPreapprovalRequest($requestEnvelope, $invest->preapproval);
            $logger->log("Created CancelPreapprovalRequest Object");
            $service = new \AdaptivePaymentsService();
            try {
                $response = $service->CancelPreapproval($cancelPreapprovalReq);
                $ack = strtoupper($response->responseEnvelope->ack);
                if ($ack == 'SUCCESS') {
                    $invest->setStatus(-1);
                }
            } catch(Exception $ex) {
                require_once 'Common/Error.php';
                exit;
            }
        }
    }
}
