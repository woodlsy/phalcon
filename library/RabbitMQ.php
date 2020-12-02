<?php
namespace woodlsy\phalcon\library;

use AMQPChannel;
use AMQPConnection;
use AMQPConnectionException;
use AMQPExchange;
use AMQPQueue;
use Phalcon\Di;

/**
 * rabbitmq 类
 * 生产消息：(new Rabbitmq($config))->set('activity', 'test', 'q_demo')->producer('测试测试哈');
 * 消费消息：$mq = (new Rabbitmq($config))->set('activity', 'test', 'q_demo');
 *        $mq->consumer();
 * 消费成功：$mq->ack();
 * 消费失败：$mq->nack();
 *
 * @create_time 2017年12月12日
 */
class RabbitMQ
{
    private $_channel;
    private $_exchange;
    private $_queue;
    private $_conn;
    private $_routeKey;
    private $_mqMsg;
    
    /**
     * 初始化
     * @param array $config   
     * array( 
     *  'host'=>'127.0.0.1',  //rabbitmq 服务器host
     *  'port'=>5672,         //rabbitmq 服务器端口
     *  'login'=>'guest',     //登录用户
     *  'password'=>'guest',   //登录密码
     *  'vhost'=>'/'         //虚拟主机
     * );
     * @throws \Exception
     * @create_time 2017年12月12日
     */
    /**
     * 初始化
     *
     * array(
     *  'host'=>'127.0.0.1',  //rabbitmq 服务器host
     *  'port'=>5672,         //rabbitmq 服务器端口
     *  'login'=>'guest',     //登录用户
     *  'password'=>'guest',   //登录密码
     *  'vhost'=>'/'         //虚拟主机
     * );
     * RabbitMQ constructor.
     * @param array $config
     * @throws AMQPConnectionException
     */
    public function __construct(array $config = null)
    {
        if (!extension_loaded('amqp')) {
            throw new \Exception('请先安装amqp扩展');
        }
        $config = empty($config) ? DI::getDefault()->get('config')->rabbitMQ->toArray() : $config;
        if(empty($config)){
            throw new \Exception('rabbitmq配置错误！');
        }
        $this->_conn = new AMQPConnection($config);
        if(!$this->_conn->connect()){
            throw new \Exception('连接Rabbitmq失败！');
        }
        $this->_channel = new AMQPChannel($this->_conn);
    }

    /**
     * 设置mq 消息交换机exchange和消息队列 queue
     *
     * @param string $exchangeName
     * @param string $routeKey
     * @param string $queueName
     * @return $this
     * @throws AMQPConnectionException
     * @throws \AMQPChannelException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function set(string $exchangeName, string $routeKey, string $queueName)
    {
        $this->_exchange = new AMQPExchange($this->_channel);
        $this->_exchange->setName($exchangeName);
        $this->_exchange->setType(AMQP_EX_TYPE_DIRECT); //direct类型
        $this->_exchange->setFlags(AMQP_DURABLE); //持久化
        $this->_exchange->declareExchange();
        $this->_queue = new AMQPQueue($this->_channel);  //创建队列
        $this->_queue->setName($queueName);  //设置队列名字 如果不存在则添加
        $this->_queue->setFlags(AMQP_DURABLE); //持久化
        $this->_queue->declareQueue();
        $this->_queue->bind($exchangeName,$routeKey);  //绑定
        $this->_routeKey = $routeKey;
        return $this;
    }

    /**
     * 生产消息
     *
     * @param $message
     * @return mixed
     * @throws \Exception
     */
    public function producer($message) {
        $message = is_array($message) || is_object($message) ? Helper::jsonEncode($message) : $message;
        if(empty($message))throw new \Exception('消息不能为空');
        try {
            $rst = $this->_exchange->publish($message, $this->_routeKey,AMQP_NOPARAM,array('delivery_mode'=>2));
            $this->_conn->disconnect();
            if(!$rst){
                throw new \Exception('写入队列失败!');
            }
            return $rst;
        }catch(\Exception $exc){
            throw new \Exception('入队失败:'.$exc->getMessage());
        }
    }

    /**
     * 消费消息
     *
     * @return bool
     */
    public function consumer()
    {
        $this->_mqMsg = $this->_queue->get();
        if($this->_mqMsg){
            $message = $this->_mqMsg->getBody();
            return $message;
        }else{
            return false;
        }
    }
    
    /**
     * 确认消费成功，删除消息
     * @throws \Exception
     * @create_time 2017年12月12日
     */
    public function ack()
    {
        if($this->_mqMsg){
            $this->_queue->ack($this->_mqMsg->getDeliveryTag());
        }else{
            throw new \Exception('消息不存在!');
        }
    }

    /**
     * 确认消费失败，消息丢回队列，等下一次重新消费
     *
     * @throws \Exception
     */
    public function nack() {
        if($this->_mqMsg){
            $this->_queue->nack($this->_mqMsg->getDeliveryTag(),16384);
        }else{
            throw new \Exception('消息不存在!');
        }
    }
}