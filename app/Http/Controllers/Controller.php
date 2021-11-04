<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController{

    /**
     * @var float
     */

    private static $MRP = 2525.00;
    private static $MZP = 42500.00;

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private function calculate($request) {
        $worked_coefficient = $request->days_worked / $request->days_norm;
        $salary = $worked_coefficient * $request->salary;

        if($request->is_disabled != 0 && $request->is_retiree != 0) {
            $OPV = $SO = $OSMS = $VOSMS = $IPN = 0;
        }
        else{
            //getting OPV
            $OPV = self::getOPV($salary, $request->is_disabled, $request->disable_group);
            //getting SO
            $SO = self::getSO($salary, $request->is_retiree, $OPV);

            //getting osms and vosms
            $OSMS = $VOSMS = 0;
            //if employee is not disabled and is not a retiree
            if($request->is_disabled == 0 && $request->is_retiree == 0) {
                $VOSMS = $salary / 50;
                $OSMS = $salary / 50;
            }

            $IPN = self::getIPN($salary, $request->is_disabled, $request->is_retiree, $request->is_mzp, $OPV, $VOSMS);

        }
        return array(
            "IPN" => $IPN,
            "OPV" => $OPV,
            "OSMS" => $OSMS,
            "VOSMS" => $VOSMS,
            "SO" => $SO,
            "salary" => $salary,
            "after_taxes" => $salary - $IPN - $OPV - $OSMS - $VOSMS
            );
    }

    public function getCalculate(Request $request) {
        //get calculation data
        $data = self::calculate($request);

        return response()->json([
            "Налоги" => array(
                "Индивидуальный подоходный налог (ИПН)" => number_format($data["IPN"], 2),
                "Обязательные пенсионные взносы (ОПВ)" => number_format($data["OPV"], 2),
                "Обязательное социальное медицинское страхование (ОСМС)" => number_format($data["OSMS"], 2),
                "Взносы на обязательное социальное медицинское Страхование (ВОСМС)" => number_format($data["VOSMS"], 2),
                "Социальные отчисления (СО)" => number_format($data["SO"], 2),
            ),
            "Начисленная зарплата " => number_format($data["salary"], 2),
            "Зарплата на руки " => number_format($data["after_taxes"], 2)
        ], 200);

    }

    public function postCalculate(Request $request) {
        //get calculation data
        $content = json_decode($request->getContent());
        if(json_last_error() != JSON_ERROR_NONE){
            return response()->json(["status" => "error", "message" => "Ошибка валидации JSON"], 400);
        }
        $data = self::calculate($content);

        $payment = new Payment();
        $payment->employee_id = $content->employee_id;
        $payment->month = $content->month;
        $payment->year = $content->year;
        $payment->days_norm = $content->days_norm;
        $payment->days_worked = $content->days_worked;
        $payment->is_mzp = $content->is_mzp;
        $payment->salary = $data["salary"];
        $payment->after_taxes = $data["after_taxes"];
        $payment->ipn = $data["IPN"];
        $payment->opv = $data["OPV"];
        $payment->osms = $data["OSMS"];
        $payment->vosms = $data["VOSMS"];
        $payment->so = $data["SO"];

        $payment->save();

        return response()->json([
            "Налоги" => array(
                "Индивидуальный подоходный налог (ИПН)" => number_format($data["IPN"], 2),
                "Обязательные пенсионные взносы (ОПВ)" => number_format($data["OPV"], 2),
                "Обязательное социальное медицинское страхование (ОСМС)" => number_format($data["OSMS"], 2),
                "Взносы на обязательное социальное медицинское Страхование (ВОСМС)" => number_format($data["VOSMS"], 2),
                "Социальные отчисления (СО)" => number_format($data["SO"], 2),
            ),
            "Начисленная зарплата " => number_format($data["salary"], 2),
            "Зарплата на руки " => number_format($data["after_taxes"], 2)
        ], 200);
    }

    private function getIPN($salary, $is_disabled, $is_retiree, $is_mzp, $OPV, $VOSMS){
        $IPN = 0;

        //check if disabled
        if($is_disabled != 0) {
            //if disabled's salary is greater than 882 * mrp
            if($salary > 882 * self::$MRP){
                //check if mzp
                $mzp = self::getMZP($is_mzp);
                //calculating ipn
                $IPN = ( $salary - $OPV - 1 * $mzp - $VOSMS -
                        self::calculateCorrect($salary, $OPV, $mzp, $VOSMS)
                    ) / 10;
            }
        }
        else{
            //check if mzp
            $mzp = self::getMZP($is_mzp);
            //calculating ipn
            $IPN = ( $salary - $OPV - 1 * $mzp - $VOSMS -
                    self::calculateCorrect($salary, $OPV, $mzp, $VOSMS)
                ) / 10;
        }

        return $IPN;
    }

    private function getMZP($is_mzp) {
        $mzp = 0;
        if($is_mzp) {
            $mzp = self::$MZP;
        }
        return $mzp;
    }

    private function calculateCorrect($salary, $OPV, $mzp, $VOSMS) {
        $correct = 0;
        //making correction if $salary is lower than 25 mrp
        if($salary < 25 * self::$MRP){
            $correct = ($salary - $OPV - $mzp - $VOSMS) * 0.9;
        }
        return $correct;


    }

    private function getOPV($salary, $is_disabled, $disable_group = null) {
        $OPV = 0;
        //check if employee is disabled
        if($is_disabled != 0) {
            //check the disabled group
            if($disable_group == 3) {
                $OPV = $salary / 10;
            }
        }
        //in case he/she is not disabled
        else{
            $OPV = $salary / 10;
        }

        return $OPV;
    }

    private function getSO($salary, $is_retiree, $OPV) {
        $SO = 0 ;

        //if employee is not a retiree
        if($is_retiree == 0) {
            $SO = ($salary - $OPV) * 0.035;
        }

        return $SO;
    }



}
