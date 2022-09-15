
#!/bin/bash

tt="OSMkYXNzMTAxQHllYWgubmV0IyRjMVJERU4jJDE2NjMyMDQ2NzIjJHg5VDlDVw=="
number=$1
for((i=1;i<=$number;i++));  
do   
echo "########################## 订单$i ##########################"
curl  --location --request GET 'https://huofu.vip/api/merchant/createBillOrder?bill_goods_num=1&bill_goods_name=1&bill_goods_price=122&bill_goods_total_price=122&bill_total_price=0&bill_send_type=2&bill_collection_email=&bill_is_fee=0&bill_fee=122&fiat_currency=USD&timeout_at=2022-09-22%2014%3A42%3A28&note=' \
--header 'token: '${tt}'' > token.txt

TOKEN=$(cat token.txt| awk -F '\"data\":\"' '{print $2}' | sed  's/\\//g' |sed 's/\"\}//g' | awk -F '=' '{print $2}')

curl  --location --request GET 'https://test1234.huofu.vip/api/pay/payment?id='${TOKEN}'&way=USDT&type=1&customerid=jkT2M4T594QqDH1vmJlb18qHc9LzpQea&_nocache=1663225037186' 

if [ $? -eq 0 ]; then

    echo -e '\n########################## 订单'$i'执行完成 ##########################'
else
    echo -e '\n########################## 订单'$i'执行失败 ##########################'
fi
done  
echo -e '\n全部执行完成'
