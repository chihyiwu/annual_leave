
<?php

/**
 * 公用元件
 */
class Annual_Leave {
    
    /**
     * 取得員工該年度特休假可休月份<br><br>
     * 特休假，設計師最多可以放10天，放在3-12月。每月1天。<br>
     * 特休不足10天著，依淡月優先排。 11.12.9.3.4.5.6.7.8.10。<br>
     * 助理不發放不休假獎金，故凡年資符合有特休假時，可於1-12月平均排休。若超過12天。1個月可排2天以上。<br>
     * 滿半年時，未休之特休可往後延，但不得延至次年。<br>     * 
     * 回傳 array(1=>'201701',...14=>'201712')
     * @param type $year - 西元年
     * @param type $empno - 員編
     * @return array - 特1-N,可休月份
     */
    public function getAnnual($year, $empno,$month=null) {
        
        // 依傳入員編. 及西元年. 判斷可休之特休假
        $ary = array();
        //特休陣列
        $Annual_leave_Ary = array();
        // 取得員工到職日. 依到職日先判斷應有幾個特休假
        
        //到職日期
        $arrivedate = '';
        
        if(isset($month) && $month !=null ) {
            
            $Ym = $year.$month;
            
            $empSql = "SELECT a.* FROM (tbs_emp_month a INNER JOIN ( SELECT `empno`, MAX( `daymonth` ) AS daymonth"
                    ." FROM tbs_emp_month WHERE `daymonth` <= '$Ym' AND empno = '$empno')b"
                    ." ON a.`empno` = b.`empno` AND a.`daymonth` = b.`daymonth` )";
        }else{

            $empSql = "SELECT a.* FROM (tbs_emp_month a INNER JOIN ( SELECT `empno`, MAX( `daymonth` ) AS daymonth"
                    ." FROM tbs_emp_month WHERE mid(`daymonth`,1,4) <= '$year' AND empno = '$empno')b"
                    ." ON a.`empno` = b.`empno` AND a.`daymonth` = b.`daymonth` )";
        }
        $tbsempmonth_Ary = TbsEmpMonth::model()->findAllBySql($empSql);
        
        foreach ($tbsempmonth_Ary as $item) {
            
            $arrivedate = $item['arrivedate'];
            
            $position = $item['position1'];
        }
        //到職日期去除"-"
        $date = explode('-', $arrivedate);
        //到職日期
        $arrivedates = date("Ymd", mktime(0, 0, 0,$date[1],$date[2],$date[0]));
        //到職-年月
        $arrivedate_Ym = date("Ym", mktime(0, 0, 0,$date[1],$date[2],$date[0]));
        //到職-年
        $arrivedate_Year = date("Y", mktime(0, 0, 0, $date[1],$date[2],$date[0]));        
        //到職-天
        $arrivedate_day = date("d", mktime(0, 0, 0,0,$date[2],0));
        //基準年年初
        //$End_year = date("Ymd", mktime(0, 0, 0,12,31,$year));
        //基準年年底
        $year_EndOfYear = date("Ymd", mktime(0, 0, 0,12,31,$year));

        //留職停薪資料
        // 2017-7-18 修改判斷為留停日期須大於到職日
        $tmp = "SELECT sum(`days`) as days FROM `tbs_emp_month_log` WHERE `datee` > '$arrivedate' AND `empno` = '$empno' AND `opt2` = '1'";
        
        $tbs_log = Yii::app()->db->createCommand($tmp)->queryAll();
        
        $Annual_tbslog = 0;
        
        if(isset($tbs_log) && $tbs_log !=NULL) {
            
            foreach ($tbs_log as $key => $value) {
                
                $days = $value['days'];
            }
                //留停扣除年資
                $Annual_tbslog = round(($days / 365),2);
                //留停扣除天數
                $Annual_tbslog_day = $days; 
        }
        //扣除年資
         $Annual_leave_Ary['tbslog'] = $Annual_tbslog;    
         
        //特休假
        $Annual_leave_num = 0;        
        //年資換算特休天數    
        $Annual_leave = $this->getAnnualLeave();
        
        //基準年-到職年 = 0，進迴圈判斷是否有半年特休
        if(($year - $arrivedate_Year) == 0) {
            //到職年月日+半年
            $arrivedate_AfterHalfYear = date("Ymd", mktime(0, 0, 0,$date[1]+6,$date[2],$date[0]));
            $arrivedate_AfterHalfYear_Month = date("m", mktime(0, 0, 0,$date[1]+6,$date[2],$date[0]));
            
            //到職年月日+半年 會在 基準年年底前達成，給3天特(按剩餘月份比例)
            if(strtotime($arrivedate_AfterHalfYear) < strtotime($year_EndOfYear)) {
                
                //以結算年份的年底 - 到職日，換算到職天數
                $arrive_day_total = (strtotime($year_EndOfYear) - strtotime($arrivedate)) / (60*60*24);
                //年資 = 總天數/365,取小數第2位
                $arrive_year = round(($arrive_day_total / 365),2);
                //年資陣列
                $Annual_leave_Ary['arrive_year'] = $arrive_year;
                //留停扣除天數>0
                if($Annual_tbslog_day > 0) {

                    //年資 = (總天數-扣除天數) / 365 ,取小數第2位
                    $arrive_year = round((($arrive_day_total-$Annual_tbslog_day) / 365),2);
                    
                    if($arrive_year >=0.5) {
                        // last_month = 12月- 滿半年的月份
                        $last_month = 12 - $arrivedate_AfterHalfYear_Month;
                        //無條件捨去
                        $Annual_leave_num = floor(($last_month / 6 ) * 3);
                    }else{
                        //無條件捨去
                        $Annual_leave_num = 0;
                    }
                }else{
                    // last_month = 12月- 滿半年的月份
                    $last_month = 12 - $arrivedate_AfterHalfYear_Month;
                    //無條件捨去
                    $Annual_leave_num = floor(($last_month / 6 ) * 3);
                }
            }else{
                //以結算年份的年底 - 到職日，換算到職天數
                $arrive_day_total = (strtotime($year_EndOfYear) - strtotime($arrivedate)) / (60*60*24);
                //年資 = 總天數/365,取小數第2位
                $arrive_year = round(($arrive_day_total / 365),2);
                //年資陣列
                $Annual_leave_Ary['arrive_year'] = $arrive_year;
            }
        }elseif(($year - $arrivedate_Year) > 0) {
            //以結算年份的年底 - 到職日，換算到職天數
            $arrive_day_total = (strtotime($year_EndOfYear) - strtotime($arrivedate)) / (60*60*24);
            //留停扣除天數>0
            if($Annual_tbslog_day > 0) {
                //年資 = (總天數-扣除天數)/365,取小數2位
                $arrive_year = round((($arrive_day_total - $Annual_tbslog_day) / 365),2) ;
                //年資-無條件捨去小數點
                $arriveyear = floor($arrive_year);
                
            }else{
                
                //年資 = 總天數/365,取小數2位
                $arrive_year = round(($arrive_day_total / 365),2) ;
                //年資-無條件捨去小數點
                $arriveyear = floor($arrive_year);
                
            }
            //年資 = 總天數/365,取小數2位
             $arrive_year = round(($arrive_day_total / 365),2) ;
            //年資陣列
            $Annual_leave_Ary['arrive_year'] = $arrive_year;
            
            if($arriveyear == 1) {
                // 如果留停>0 && 工作年資 - 實際年資 > 0 
                // 新到職日 = 到職日 + 留停天數
                if($Annual_tbslog_day > 0 && ($arrive_year-($arrive_year-$Annual_tbslog))>0) {
                    //新到職日期
                    $arrivedate = date("Y-m-d", mktime(0, 0, 0,$date[1],$date[2]+$Annual_tbslog_day,$date[0]));
                    //到職日期去除"-"
                    $date = explode('-', $arrivedate);
                    //到職滿一年
                    $arrivedate_AfterYear = date("Ymd", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    
                    $arrivedate_AfterYear_Month = date("m", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    //上半年
                    $First_half_year = $arrivedate_AfterYear_Month;
                }else{
                
                    //到職滿一年
                    $arrivedate_AfterYear = date("Ymd", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    $arrivedate_AfterYear_Month = date("m", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    //上半年
                    $First_half_year = $arrivedate_AfterYear_Month;
                }
                //上半年超過6個月，直接給3天特休
                if($First_half_year >= 6) {
                    
                    $Annual_leave_first_half_num = 3;
                }else{
                    //有滿半年的條件，給3天特(按剩餘月份比例)
                    $Annual_leave_first_half_num = ceil(($First_half_year / 6 ) * 3);
                }
                                
                //下半年，如果到職日是當月1號，則+1
                if($arrivedate_day == '1') {
                    $Second_half_year = (12 - $arrivedate_AfterYear_Month) + 1;
                }else{
                    $Second_half_year = 12 - $arrivedate_AfterYear_Month;
                }
                
                if($Second_half_year > 0) {
                    
                    //下半年特休-無條件捨去
                    $Annual_leave_second_half_num = floor(($Second_half_year / 12 ) * 7);
                }else{
                    //如果12月到職，則下半年無特休
                    $Annual_leave_second_half_num = 0;
                }
                
                //特休假天數 = 上半年3天特 + 下半年滿一年7天特(按比例)
                $Annual_leave_num = $Annual_leave_first_half_num + $Annual_leave_second_half_num;
                
            }elseif($arriveyear >= 2) {
                
                //年資滿2年(含以上)，要算上年度的比例(按比例)
                $arrive_years = $arriveyear - 1;
                //上年度特休天數，如果有扣除年資要先扣除
                $before_year_Annual_leave_num = $Annual_leave[$arrive_years];
                //今年度特休天數，如果有扣除年資要先扣除
                $After_year_Annual_leave_num = $Annual_leave[$arriveyear];
                
                // 如果留停>0 && 工作年資 - 實際年資 > 0 
                // 新到職日 = 到職日 + 留停天數
                if($Annual_tbslog_day > 0 && ($arrive_year-($arrive_year-$Annual_tbslog))>0) {
                    //新到職日期
                    $arrivedate = date("Y-m-d", mktime(0, 0, 0,$date[1],$date[2]+$Annual_tbslog_day,$date[0]));
                    //到職日期去除"-"
                    $date = explode('-', $arrivedate);
                    //到職滿一年
                    $arrivedate_AfterYear = date("Ymd", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    
                    $arrivedate_AfterYear_Month = date("m", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    //上半年
                    $First_half_year = $arrivedate_AfterYear_Month -1;
                }else{
                
                    //到職 >= 2年
                    $arrivedate_AfterYear = date("Ymd", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    $arrivedate_AfterYear_Month = date("m", mktime(0, 0, 0,$date[1],$date[2],$date[0]+1));
                    //上半年
                    $First_half_year = $arrivedate_AfterYear_Month - 1;
                }
                //上半年特休-無條件進位
                $Annual_leave_first_half_num = ceil(($First_half_year / 12 ) * $before_year_Annual_leave_num);
                //下半年
                $Second_half_year = (12 - $arrivedate_AfterYear_Month) + 1;
                //下半年特休-無條件捨去
                $Annual_leave_second_half_num = floor(($Second_half_year / 12 ) * $After_year_Annual_leave_num);
                //特休假
                $Annual_leave_num = $Annual_leave_first_half_num + $Annual_leave_second_half_num;
                
            }else{
                //扣完年資低於1年，滿半年3天特 or 沒有
                if($arriveyear >= 0.5) {
                    
                    // last_month = 12月- 滿半年的月份
                    $last_month = 12 - $arrivedate_AfterHalfYear_Month;
                    //無條件捨去
                    $Annual_leave_num = floor(($last_month / 6 ) * 3);
                }else{
                    
                    $Annual_leave_num = 0;
                }
            }
        }

        if($Annual_leave_num > 0 ) {
            //職位 = 助理(17)  
            if($position == 17){

                //特休天數換對應月份-助理特休    
                $Annual_leave_Ary = $this->getAN_Annual_leave($Annual_leave_Ary,$Annual_leave_num);
            }else{
                
                //特休天數換對應月份-設計師特休    
                $Annual_leave_Ary = $this->getDN_Annual_leave($Annual_leave_Ary,$Annual_leave_num);
            }
        }
        
        //年資天數
        $Annual_leave_Ary['Annual_leave_num'] = $Annual_leave_num;
        
        //特休假休假紀錄，而且>=到職日
        $log_Ary = "SELECT `logday`,`memo` FROM `tba_log` WHERE mid(`logday`,1,4) = '$year' AND `logitem` = '8' AND `empno` = '$empno' AND `logday` >= '$arrivedates'";

        $tba_log = Yii::app()->db->createCommand($log_Ary)->queryAll();
        
        if(isset($tba_log) && $tba_log !=NULL) {

                $Annual_leave_Ary['tbalog'] = $tba_log;                
        }
        
        return array(
          
            $Annual_leave_Ary
        );
    }
    
    /**
     *  年資換算特休天數
     *  年資 => 特休天
     */
    private function getAnnualLeave() {
        
        $Annual_leave = array(
            
                 '0.5'  =>3, 
                   '1'  =>7,
                   '2'  =>10,
                   '3'  =>14,
                   '4'  =>14,
                   '5'  =>15,
                   '6'  =>15,
                   '7'  =>15,
                   '8'  =>15,
                   '9'  =>15,
                   '10' =>16,
                   '11' =>17,
                   '12' =>18,
                   '13' =>19,
                   '14' =>20,
                   '15' =>21,
                   '16' =>22,
                   '17' =>23,
                   '18' =>24,
                   '19' =>25,
                   '20' =>26,
                   '21' =>27,
                   '22' =>28,
                   '23' =>29,
                   '24' =>30,
                   '25' =>30,
                );
        
        return $Annual_leave;    
    }
}