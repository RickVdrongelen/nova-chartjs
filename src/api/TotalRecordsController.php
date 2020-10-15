<?php

namespace Coroowicaksono\ChartJsIntegration\Api;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;

class TotalRecordsController extends Controller
{
    use ValidatesRequests;
    /**
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(NovaRequest $request)
    {
        if ($request->input('model')) {
            $request->merge(['model' => urldecode($request->input('model'))]);
        }
        $showTotal = isset($request->options) ? json_decode($request->options, true)['showTotal'] ?? true : true;
        $chartType = $request->type ?? 'bar';
        $advanceFilterSelected = isset($request->options) ? json_decode($request->options, true)['advanceFilterSelected'] ?? false : false;
        $dataForLast = isset($request->options) ? json_decode($request->options, true)['latestData'] ?? 3 : 3;
        $unitOfMeasurement = isset($request->options) ? json_decode($request->options, true)['uom'] ?? 'month' : 'month';
        $startWeek = isset($request->options) ? json_decode($request->options, true)['startWeek'] ?? '1' : '1';

        if(!in_array($unitOfMeasurement, ['day', 'week', 'month', 'hour'])){
            throw new ThrowError('UOM not defined correctly. <br/>Check documentation: https://github.com/coroo/nova-chartjs');
        }

        $dateColumn = isset($request->options) ? json_decode($request->options, true)['dateColumn'] ?? 'created_at' : 'created_at';
        $calculation = isset($request->options) ? json_decode($request->options, true)['sum'] ?? 1 : 1;
        $modelValues = isset($request->options) ? json_decode($request->options, true)['modelValues'] ?? 1 : 1;

        $request->validate(['model'   => ['bail', 'required', 'min:1', 'string']]);
        $model = $request->input('model');
        $modelInstance = new $model;
        $connectionName = $modelInstance->getConnection()->getDriverName();
        $tableName = $modelInstance->getConnection()->getTablePrefix() . $modelInstance->getTable();
        $xAxisColumn = $request->input('col_xaxis') ?? DB::raw($tableName.".$dateColumn");
        $cacheKey = hash('md4', $model . (int)(bool)$request->input('expires'));
        $dataSet = Cache::get($cacheKey);
        if (!$dataSet) {
            $labelList = [];
            $xAxis = [];
            $yAxis = [];
            $seriesSql = "";
            $defaultColor = array("#ffcc5c","#91e8e1","#ff6f69","#88d8b0","#b088d8","#d8b088", "#88b0d8", "#6f69ff","#7cb5ec","#434348","#90ed7d","#8085e9","#f7a35c","#f15c80","#e4d354","#2b908f","#f45b5b","#91e8e1","#E27D60","#85DCB","#E8A87C","#C38D9E","#41B3A3","#67c4a7","#992667","#ff4040","#ff7373","#d2d2d2");
            if(isset($request->series)){
                foreach($request->series as $seriesKey => $serieslist){
                    $seriesData = json_decode($serieslist);
                    $filter = $seriesData->filter;
                    $labelList[$seriesKey] = $seriesData->label;
                    if(empty($filter->value)){
                        if($modelValues) {
                            foreach($filter as $keyFilter => $listFilter) {
                                $seriesSql .= ", SUM($listFilter)";
                                $seriesSql .= " AS '$labelList[$seriesKey]'";
                            }
                        } else {
                            $seriesSql .= ", SUM(CASE WHEN ";
                            $countFilter = count($filter);
                            foreach($filter as $keyFilter => $listFilter){
                                $seriesSql .= " ".$listFilter->key." ".($listFilter->operator ?? "=")." '".$listFilter->value."' ";
                                $seriesSql .= $countFilter-1 != $keyFilter ? " AND " : "";
                            }
                            $seriesSql .= "then ".$calculation." else 0 end) as '".$labelList[$seriesKey]."'";
                        }
                    } else {
                        $seriesSql .= ", SUM(CASE WHEN ".$filter->key." ".($filter->operator ?? "=")." '".$filter->value."' then ".$calculation." else 0 end) as '".$labelList[$seriesKey]."'";
                    }
                }
            }

            $totalsSql = "";
            if(isset($request->totalData)) {
                foreach ($request->totalData as $totalDataKey => $totalDataList) {
                    $data = json_decode($totalDataList);
                    $filter = $data->filter;
                    $labelList[$totalDataKey] = $data->label;
                    if(empty($filter->value)) {
                        if($modelValues) {
                            foreach($filter as $keyFilter => $listFilter) {
                                $totalsSql .= ", SUM($listFilter)";
                                $totalsSql .= " AS '$labelList[$totalDataKey]'";
                            }
                        } else {
                            $totalsSql .= ", SUM(CASE WHEN ";
                            $countFilter = count($filter);
                            foreach($filter as $keyFilter => $listFilter){
                                $totalsSql .= " ".$listFilter->key." ".($listFilter->operator ?? "=")." '".$listFilter->value."' ";
                                $totalsSql .= $countFilter-1 != $keyFilter ? " AND " : "";
                            }
                            $totalsSql .= "then ".$calculation." else 0 end) as '".$labelList[$totalDataKey]."'";
                        }
                    } else {
                        $totalsSql .= ", SUM(CASE WHEN ".$filter->key." ".($filter->operator ?? "=")." '".$filter->value."' then ".$calculation." else 0 end) as '".$labelList[$totalDataKey]."'";
                    }
                }
            }

            if($unitOfMeasurement=='day'){
                if(isset($request->join)){
                    $joinInformation = json_decode($request->join, true);
                    $query = $model::selectRaw('DATE('.$xAxisColumn.') AS cat, DATE('.$xAxisColumn.') AS catorder, sum('.$calculation.') counted'.$seriesSql)
                        ->join($joinInformation['joinTable'], $joinInformation['joinColumnFirst'], $joinInformation['joinEqual'], $joinInformation['joinColumnSecond']);
                } else {
                    $query = $model::selectRaw('DATE('.$xAxisColumn.') AS cat, DATE('.$xAxisColumn.') AS catorder, sum('.$calculation.') counted'.$seriesSql);
                    $totalQuery = $model::selectRaw('DATE('.$xAxisColumn.') AS cat, DATE('.$xAxisColumn.') AS catorder, sum('.$calculation.') counted'.$seriesSql);
                }
                
                if(is_numeric($advanceFilterSelected)){
                    $query->where($xAxisColumn, '>=', Carbon::now()->subDays($advanceFilterSelected));
                    if(isset($request->totalData)){
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->subDays($advanceFilterSelected));
                    }
                }
                else if($advanceFilterSelected=='YTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subYear(1));
                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subYear(1));
                    }
                }
                else if($advanceFilterSelected=='QTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonths(2));
                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonths(2));
                    }

                }
                else if($advanceFilterSelected=='MTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth());
                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth());
                    }
                }
                else if($dataForLast != '*') {
                    $query->where($xAxisColumn, '>=', Carbon::now()->subDay($dataForLast+1));
                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->subDay($dataForLast + 1));
                    }
                }
                $query->groupBy('catorder', 'cat')
                    ->orderBy('catorder', 'asc');
                if(isset($request->totalData)) {
                    $totalQuery->groupBy('catorder', 'cat')
                        ->orderBy('catorder', 'asc');
                }
            } else if($unitOfMeasurement=='week'){
                if(isset($request->join)){
                    $joinInformation = json_decode($request->join, true);
                    if($connectionName == 'pgsql'){
                        $query = $model::selectRaw("to_char(DATE_TRUNC('week', ".$xAxisColumn."), 'YYYYWW') AS cat, to_char(DATE_TRUNC('week', ".$xAxisColumn."), 'YYYYWW') AS catorder, sum(".$calculation.") counted".$seriesSql)
                            ->join($joinInformation['joinTable'], $joinInformation['joinColumnFirst'], $joinInformation['joinEqual'], $joinInformation['joinColumnSecond']);
                    } else {
                        $query = $model::selectRaw('YEARWEEK('.$xAxisColumn.', '.$startWeek.') AS cat, YEARWEEK('.$xAxisColumn.', '.$startWeek.') AS catorder, sum('.$calculation.') counted'.$seriesSql)
                            ->join($joinInformation['joinTable'], $joinInformation['joinColumnFirst'], $joinInformation['joinEqual'], $joinInformation['joinColumnSecond']);
                    }
                } else {
                    if($connectionName == 'pgsql'){
                        $query = $model::selectRaw("to_char(DATE_TRUNC('week', ".$xAxisColumn."), 'YYYYWW') AS cat, to_char(DATE_TRUNC('week', ".$xAxisColumn."), 'YYYYWW') AS catorder, sum(".$calculation.") counted".$seriesSql);
                    } else {
                        $query = $model::selectRaw('YEARWEEK('.$xAxisColumn.', '.$startWeek.') AS cat, YEARWEEK('.$xAxisColumn.', '.$startWeek.') AS catorder, sum('.$calculation.') counted'.$seriesSql);
                    }
                }
                
                if(is_numeric($advanceFilterSelected)){
                    $query->where($xAxisColumn, '>=', Carbon::now()->subDays($advanceFilterSelected));
                }
                else if($advanceFilterSelected=='YTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subYear(1));
                }
                else if($advanceFilterSelected=='QTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonths(2));
                }
                else if($advanceFilterSelected=='MTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth());
                }
                else if($dataForLast != '*') {
                    $query->where($xAxisColumn, '>=', Carbon::now()->startOfWeek()->subWeek($dataForLast));
                }
                $query->groupBy('catorder', 'cat')
                    ->orderBy('catorder', 'asc');
            } else if($unitOfMeasurement=='hour'){
                if(isset($request->join)){
                    $joinInformation = json_decode($request->join, true);
                    $query = $model::selectRaw('HOUR('.$xAxisColumn.') AS cat, HOUR('.$xAxisColumn.') AS catorder, sum('.$calculation.') counted'.$seriesSql)
                        ->join($joinInformation['joinTable'], $joinInformation['joinColumnFirst'], $joinInformation['joinEqual'], $joinInformation['joinColumnSecond']);
                } else {
                    $query = $model::selectRaw('HOUR('.$xAxisColumn.') AS cat, HOUR('.$xAxisColumn.') AS catorder, sum('.$calculation.') counted'.$seriesSql);
                }

                if(is_numeric($advanceFilterSelected)){
                    $query->where($xAxisColumn, '>=', Carbon::now()->subDays($advanceFilterSelected));
                }
                else if($advanceFilterSelected=='YTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subYear(1));
                }
                else if($advanceFilterSelected=='QTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonths(2));
                }
                else if($advanceFilterSelected=='MTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth());
                }
                else if($dataForLast != '*') {
                    $query->where($xAxisColumn, '>=', Carbon::now()->startOfDay());
                }
                $query->groupBy('catorder', 'cat')
                    ->orderBy('catorder', 'asc');

            } else {
                if(isset($request->join)){
                    $joinInformation = json_decode($request->join, true);
                    if($connectionName == 'pgsql'){
                        $query = $model::selectRaw("to_char(".$xAxisColumn.", 'Mon YYYY') AS cat, to_char(".$xAxisColumn.", 'YYYY-MM') AS catorder, sum(".$calculation.") counted".$seriesSql)
                            ->join($joinInformation['joinTable'], $joinInformation['joinColumnFirst'], $joinInformation['joinEqual'], $joinInformation['joinColumnSecond']);
                    } else {
                        $query = $model::selectRaw('DATE_FORMAT('.$xAxisColumn.', "%b %Y") AS cat, DATE_FORMAT('.$xAxisColumn.', "%Y-%m") AS catorder, sum('.$calculation.') counted'.$seriesSql)
                            ->join($joinInformation['joinTable'], $joinInformation['joinColumnFirst'], $joinInformation['joinEqual'], $joinInformation['joinColumnSecond']);
                    }
                } else {
                    if($connectionName == 'pgsql'){
                        $query = $model::selectRaw("to_char(".$xAxisColumn.", 'Mon YYYY') AS cat, to_char(".$xAxisColumn.", 'YYYY-MM') AS catorder, sum(".$calculation.") counted".$seriesSql);
                    } else {
                        $query = $model::selectRaw('DATE_FORMAT('.$xAxisColumn.', "%b %Y") AS cat, DATE_FORMAT('.$xAxisColumn.', "%Y-%m") AS catorder, sum('.$calculation.') counted'.$seriesSql);
                        $totalQuery = $model::selectRaw('sum('.$calculation.') counted'.$totalsSql);
                    }
                }
                
                if(is_numeric($advanceFilterSelected)){
                    $query->where($xAxisColumn, '>=', Carbon::now()->subDays($advanceFilterSelected));

                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->subDays($advanceFilterSelected));
                    }
                }
                else if($advanceFilterSelected=='YTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subYear(1));

                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subYear(1));
                    }
                }
                else if($advanceFilterSelected=='QTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonths(2));

                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonths(2));
                    }
                }
                else if($advanceFilterSelected=='MTD'){
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth());
                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth());
                    }
                }
                else if($dataForLast != '*') {
                    $query->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonth($dataForLast-1));
                    if(isset($request->totalData)) {
                        $totalQuery->where($xAxisColumn, '>=', Carbon::now()->firstOfMonth()->subMonth($dataForLast-1));
                    }
                }
                $query->groupBy('catorder', 'cat')
                    ->orderBy('catorder', 'asc');
            }

            if(isset(json_decode($request->options, true)['queryFilter'])){
                $queryFilter = json_decode($request->options, true)['queryFilter'];
                foreach($queryFilter as $qF){
                    if(isset($qF['value'])){
                        if(isset($qF['operator'])){
                            $query->where($qF['key'], $qF['operator'], $qF['value']);
                            if(isset($request->totalData)) {
                                $totalQuery->where($qF['key'], $qF['operator'], $qF['value']);
                            }
                        } else {
                            $query->where($qF['key'], $qF['value']);
                            if(isset($request->totalData)) {
                                $totalQuery->where($qF['key'], $qF['value']);
                            }
                        }
                    } else {
                        if($qF['operator']=='IS NULL'){
                            $query->whereNull($qF['key']);
                            if(isset($request->totalData)) {
                                $totalQuery->whereNull($qF['key']);
                            }
                        } else if($qF['operator']=='IS NOT NULL'){
                            $query->whereNotNull($qF['key']);
                            if(isset($request->totalData)) {
                                $totalQuery->whereNotNull($qF['key']);
                            }
                        }
                    }
                }
            }

            $dataSet = $query->get();

            $xAxis = collect($dataSet)->map(function ($item, $key) use ($unitOfMeasurement){
                if($unitOfMeasurement=='week'){
                    $splitCat = str_split($item->only(['cat'])['cat'], 4);
                    $cat = "W".$splitCat[1]." ".$splitCat[0];
                } else {
                    $cat = $item->only(['cat'])['cat'];
                }
                return $cat;
            });

            if(isset($request->series)){
                $countKey = 0;
                foreach($request->series as $sKey => $sData){
                    $dataSeries = json_decode($sData);
                    $filter = $dataSeries->filter;
                    $yAxis[$sKey]['label'] = $dataSeries->label;
                    if(isset($dataSeries->fill)){
                        if($dataSeries->fill==false){
                            $yAxis[$sKey]['borderColor'] = $dataSeries->backgroundColor ?? $defaultColor[$sKey];
                            $yAxis[$sKey]['fill'] = false;
                        } else {
                            $yAxis[$sKey]['backgroundColor'] = $dataSeries->backgroundColor ?? $defaultColor[$sKey];
                        }
                    } else {
                        $yAxis[$sKey]['backgroundColor'] = $dataSeries->backgroundColor ?? $defaultColor[$sKey];
                    }
                    $yAxis[$sKey]['data'] = collect($dataSet)->map(function ($item, $key) use ($dataSeries){
                        return $item->only([$dataSeries->label])[$dataSeries->label];
                    });
                    $countKey++;
                }
                if($showTotal == true){
                    $yAxis[$countKey] = $this->counted($dataSet, $defaultColor[$countKey], 'line');
                }
            } else {
                $yAxis[0] = $this->counted($dataSet, $defaultColor[0], $chartType);
            }
            if ($request->input('expires')) {
                Cache::put($cacheKey, $dataSet, Carbon::parse($request->input('expires')));
            }
        }

        $totals = [];
        if(isset($request->totalData)) {
            $totals = $totalQuery->first()->toArray();
        }
        return response()->json(
            ['dataset' => [
                'xAxis'  => $xAxis,
                'yAxis'  => $yAxis,
            ], 'totals' => $totals,
        ]);
    }
    
    private function counted($dataSet, $bgColor = "#111", $type = "bar")
    {
        $yAxis = [
            'type'  => $type,
            'label' => 'Total',
            'data' => collect($dataSet)->map(function ($item, $key) {
                return $item->only(['counted'])['counted'];
            })
        ];
        if($type=="line"){
            $yAxis['fill'] = false;
            $yAxis['borderColor'] = $bgColor;
        } else {
            $yAxis['backgroundColor'] = $bgColor;
        }
        return $yAxis;
    }
}
