#!/bin/bash


if [ $# -eq 2 ] && [[ "$2" != "" ]]; then

    echo TOKEN_ENV=$2 > .token_env

fi

source .token_env
tt=$TOKEN_ENV

number=$1

#记录执行成功的数量

sum=0

for((i=1;i<=$number;i++));  
do  
echo "########################## 订单$i ##########################"
##生成订单
#curl --location --request GET 'https://huofu.vip/api/merchant/createBillOrder?bill_goods_num=1&bill_goods_name=1&bill_goods_price=122&bill_goods_total_price=122&bill_total_price=0&bill_send_type=2&bill_collection_email=&bill_is_fee=0&bill_fee=122&fiat_currency=USD&timeout_at=2022-09-22%2014%3A42%3A28&note=' \
#--header 'token: '${tt}'' > token.txt
curl --location --request GET 'https://huofu.vip/api/merchant/createBillOrder?bill_goods_num=1&bill_goods_name=1&bill_goods_price=1.01&bill_goods_total_price=1.01&bill_total_price=0&bill_send_type=2&bill_collection_email=&bill_is_fee=0&bill_fee=1.01&fiat_currency=EUR&timeout_at=2022-10-24%2023%3A59%3A59&note=1' \
--header 'token: '${tt}'' > token.txt


data=$(awk -F ':' '{print $4}' token.txt)


if [[ "$data" == "[]}" ]];then
    echo -e "ERROR: token已失效，请重新输入token"
    break
else   
    TOKEN=$(cat token.txt| awk -F '\"data\":\"' '{print $2}' | sed 's/\\//g' |sed 's/\"\}//g' | awk -F '=' '{print $2}')
##支付

   # curl --location --request GET 'https://test1234.huofu.vip/api/pay/payment?id='${TOKEN}'&way=USDT&type=1&customerid=jkT2M4T594QqDH1vmJlb18qHc9LzpQea&_nocache=1663225037186' 
    curl --location --request GET 'https://test1234.huofu.vip/api/pay/payment?id='${TOKEN}'&way=USDT&type=1&customerid=JS6tEF8gK8WNm796cysv2XjPSBqZo7yy&_nocache=1666576794734'

    if [ $? -eq 0 ]; then
         echo -e '\n########################## 订单'$i'执行完成 ##########################'        
         # 执行成功计数+1
         sum=`expr $sum + 1`
     else
         echo -e '\n########################## 订单'$i'执行失败 ##########################'
    fi
fi
done

if [ $sum -ne $1 ];then
    echo -e '执行未完成'
else
    echo -e '全部执行成功'
fi
