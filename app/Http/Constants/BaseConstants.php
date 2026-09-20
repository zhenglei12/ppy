<?php


namespace App\Http\Constants;


interface BaseConstants
{
    const METAL = [
        1 => "自理",
        2 => "含早",
        3 => "含中",
        4 => "含晚",
        5 => "早中",
        6 => "早晚",
        7 => "中晚",
        8 => "早中晚"
    ];
    const STAY = [
        1 => "自理",
        2 => "行程安排"
    ];

    const TASKTYPE = [
        1 => "文章写作",
        2 => "文章修改",
        3 => "润色降重",
        4 => "Office服务",
        5 => "其他"
    ];


    const EDUCATION = [
        1 => "专科",
        2 => "本科",
        3 => "硕",
        4 => "其他"
    ];

    const ORDERPAYTYPE = [
        1 => "支付宝",
        2 => "微信",
        3 => "银行转账",
        4 => "对公账户",
        5 => "线上支付"
    ];

    const ORDERSTARTLIST = [
        -1 => "等待安排",
        1 => "写作进行",
        2 => "打回修改",
        3 => "订单完成",
        4 => "提交客户",
        5 => "已交稿",
        6 => "收款中"
    ];

    const FINANCE = [
        -1 => "否",
        1 => "已审核",
    ];
}
