<?php

Yii::import('system.db.CDbConnection');
Yii::import('system.db.ar.CActiveRecord');

if(!defined('SRC_DB_FILE'))
	define('SRC_DB_FILE',dirname(__FILE__).'/../data/source.db');
if(!defined('TEST_DB_FILE'))
	define('TEST_DB_FILE',dirname(__FILE__).'/../data/test.db');

require_once(dirname(__FILE__).'/../data/models.php');

class CActiveRecordTest extends CTestCase
{
	private $app;
	private $db;

	public function setUp()
	{
		if(!extension_loaded('pdo') || !extension_loaded('pdo_sqlite'))
			$this->markTestSkipped('PDO and SQLite extensions are required.');
		copy(SRC_DB_FILE,TEST_DB_FILE);

		$config=array(
			'basePath'=>dirname(__FILE__),
			'components'=>array(
				'db'=>array(
					'class'=>'system.db.CDbConnection',
					'connectionString'=>'sqlite:'.TEST_DB_FILE,
				),
			),
		);
		$app=new TestWebApplication($config);
		$app->db->active=true;
		CActiveRecord::$db=$this->db=$app->db;
	}

	public function tearDown()
	{
		if($this->db)
			$this->db->active=false;
	}

	public function testModel()
	{
		$model=Post::model();
		$this->assertTrue($model instanceof Post);
		$this->assertTrue($model->dbConnection===$this->db);
		$this->assertTrue($model->dbConnection->active);
		$this->assertEquals('posts',$model->tableName());
		$this->assertEquals('id',$model->tableSchema->primaryKey);
		$this->assertTrue($model->tableSchema->sequenceName==='');
		$this->assertEquals(array(),$model->attributeLabels());
		$this->assertEquals(array(),$model->protectedAttributes());
		$this->assertEquals(array(),$model->rules());
		$this->assertEquals(array(
			'author'=>array(CActiveRecord::BELONGS_TO,'User','author_id'),
			'firstComment'=>array(CActiveRecord::HAS_ONE,'Comment','post_id','order'=>'??.content'),
			'comments'=>array(CActiveRecord::HAS_MANY,'Comment','post_id','order'=>'??.content DESC'),
			'categories'=>array(CActiveRecord::MANY_MANY,'Category','post_category(post_id,category_id)')),$model->relations());
		$this->assertEquals('Id',$model->getAttributeLabel('id'));
		$this->assertEquals('Author Id',$model->getAttributeLabel('author_id'));
		$this->assertTrue($model->getActiveRelation('author') instanceof CBelongsToRelation);
		$this->assertTrue($model->tableSchema instanceof CDbTableSchema);
		$this->assertTrue($model->commandBuilder instanceof CDbCommandBuilder);
		$this->assertTrue($model->hasAttribute('id'));
		$this->assertFalse($model->hasAttribute('comments'));
		$this->assertFalse($model->hasAttribute('foo'));
		$this->assertEquals(array('id'=>null,'title'=>null,'create_time'=>null,'author_id'=>null,'content'=>null),$model->attributes);

		$post=new Post;
		$this->assertNull($post->id);
		$this->assertNull($post->title);
		$post->setAttributes(array('id'=>3,'title'=>'test title'));
		$this->assertNull($post->id);
		$this->assertEquals('test title',$post->title);
	}

	public function testFind()
	{
		// test find() with various parameters
		$post=Post::model()->find();
		$this->assertTrue($post instanceof Post);
		$this->assertEquals(1,$post->id);

		$post=Post::model()->find('id=5');
		$this->assertTrue($post instanceof Post);
		$this->assertEquals(5,$post->id);

		$post=Post::model()->find('id=:id',array(':id'=>2));
		$this->assertTrue($post instanceof Post);
		$this->assertEquals(2,$post->id);

		$post=Post::model()->find(array('condition'=>'id=:id','params'=>array(':id'=>3)));
		$this->assertTrue($post instanceof Post);
		$this->assertEquals(3,$post->id);

		// test find() without result
		$post=Post::model()->find('id=6');
		$this->assertNull($post);

		// test findAll() with various parameters
		$posts=Post::model()->findAll();
		$this->assertEquals(5,count($posts));
		$this->assertTrue($posts[3] instanceof Post);
		$this->assertEquals(4,$posts[3]->id);

		$posts=Post::model()->findAll(new CDbCriteria(array('limit'=>3,'offset'=>1)));
		$this->assertEquals(3,count($posts));
		$this->assertTrue($posts[2] instanceof Post);
		$this->assertEquals(4,$posts[2]->id);

		// test findAll() without result
		$posts=Post::model()->findAll('id=6');
		$this->assertTrue($posts===array());

		// test findByPk
		$post=Post::model()->findByPk(2);
		$this->assertEquals(2,$post->id);

		$post=Post::model()->findByPk(array(3,2));
		$this->assertEquals(2,$post->id);

		$post=Post::model()->findByPk(array());
		$this->assertNull($post);

		$post=Post::model()->findByPk(6);
		$this->assertNull($post);

		// test findAllByPk
		$posts=Post::model()->findAllByPk(2);
		$this->assertEquals(1,count($posts));
		$this->assertEquals(2,$posts[0]->id);

		$posts=Post::model()->findAllByPk(array(4,3,2),'id<4');
		$this->assertEquals(2,count($posts));
		$this->assertEquals(2,$posts[0]->id);
		$this->assertEquals(3,$posts[1]->id);

		$posts=Post::model()->findAllByPk(array());
		$this->assertTrue($posts===array());

		// test findByAttributes
		$post=Post::model()->findByAttributes(array('author_id'=>2),array('order'=>'id DESC'));
		$this->assertEquals(4,$post->id);

		// test findAllByAttributes
		$posts=Post::model()->findAllByAttributes(array('author_id'=>2));
		$this->assertEquals(3,count($posts));

		// test findBySql
		$post=Post::model()->findBySql('select * from posts where id=:id',array(':id'=>2));
		$this->assertEquals(2,$post->id);

		// test findAllBySql
		$posts=Post::model()->findAllBySql('select * from posts where id>:id',array(':id'=>2));
		$this->assertEquals(3,count($posts));

		// test count
		$this->assertEquals(5,Post::model()->count());
		$this->assertEquals(3,Post::model()->count(array('condition'=>'id>2')));

		// test countBySql
		$this->assertEquals(1,Post::model()->countBySql('select id from posts limit 1'));

		// test exists
		$this->assertTrue(Post::model()->exists('id=:id',array(':id'=>1)));
		$this->assertFalse(Post::model()->exists('id=:id',array(':id'=>6)));
	}

	public function testInsert()
	{
		$post=new Post;
		$this->assertEquals(array('id'=>null,'title'=>null,'create_time'=>null,'author_id'=>null,'content'=>null),$post->attributes);
		$post->title='test post 1';
		$post->create_time=time();
		$post->author_id=1;
		$post->content='test post content 1';
		$this->assertTrue($post->isNewRecord);
		$this->assertNull($post->id);
		$this->assertTrue($post->save());
		$this->assertEquals(array(
			'id'=>6,
			'title'=>'test post 1',
			'create_time'=>$post->create_time,
			'author_id'=>1,
			'content'=>'test post content 1'),$post->attributes);
		$this->assertFalse($post->isNewRecord);
		$this->assertEquals($post->attributes,Post::model()->findByPk($post->id)->attributes);
	}

	public function testUpdate()
	{
		// test save
		$post=Post::model()->findByPk(1);
		$this->assertFalse($post->isNewRecord);
		$this->assertEquals('post 1',$post->title);
		$post->title='test post 1';
		$this->assertTrue($post->save());
		$this->assertFalse($post->isNewRecord);
		$this->assertEquals('test post 1',$post->title);
		$this->assertEquals('test post 1',Post::model()->findByPk(1)->title);

		// test saveAttributes
		$post->author_id=3;
		$post->content='new content';
		$this->assertTrue($post->saveAttributes(array('author_id','title'=>'new post')));
		$this->assertEquals(array(
			'id'=>'1',
			'title'=>'new post',
			'author_id'=>'3',
			'create_time'=>'100000',
			'content'=>'content 1'),Post::model()->findByPk(1)->attributes);

		// test updateByPk
		$this->assertEquals(2,Post::model()->updateByPk(array(4,5),array('title'=>'test post')));
		$this->assertEquals('post 2',Post::model()->findByPk(2)->title);
		$this->assertEquals('test post',Post::model()->findByPk(4)->title);
		$this->assertEquals('test post',Post::model()->findByPk(5)->title);

		// test updateAll
		$this->assertEquals('new post',Post::model()->findByPk(1)->title);
		$this->assertEquals(1,Post::model()->updateAll(array('title'=>'test post'),'id=1'));
		$this->assertEquals('test post',Post::model()->findByPk(1)->title);

		// test updateCounters
		$this->assertEquals(2,Post::model()->findByPk(2)->author_id);
		$this->assertEquals(2,Post::model()->findByPk(3)->author_id);
		$this->assertEquals(2,Post::model()->findByPk(4)->author_id);
		$this->assertEquals(3,Post::model()->updateCounters(array('author_id'=>-1),'id>2'));
		$this->assertEquals(2,Post::model()->findByPk(2)->author_id);
		$this->assertEquals(1,Post::model()->findByPk(3)->author_id);
	}

	public function testDelete()
	{
		$post=Post::model()->findByPk(1);
		$this->assertTrue($post->delete());
		$this->assertNull(Post::model()->findByPk(1));

		$this->assertTrue(Post::model()->findByPk(2) instanceof Post);
		$this->assertTrue(Post::model()->findByPk(3) instanceof Post);
		$this->assertEquals(2,Post::model()->deleteByPk(array(2,3)));
		$this->assertNull(Post::model()->findByPk(2));
		$this->assertNull(Post::model()->findByPk(3));

		$this->assertTrue(Post::model()->findByPk(5) instanceof Post);
		$this->assertEquals(1,Post::model()->deleteAll('id=5'));
		$this->assertNull(Post::model()->findByPk(5));
	}

	public function testRefresh()
	{
		$post=Post::model()->findByPk(1);
		$post2=Post::model()->findByPk(1);
		$post2->saveAttributes(array('title'=>'new post'));
		$this->assertEquals('post 1',$post->title);
		$this->assertTrue($post->refresh());
		$this->assertEquals('new post',$post->title);
	}

	public function testEquals()
	{
		$post=Post::model()->findByPk(1);
		$post2=Post::model()->findByPk(1);
		$post3=Post::model()->findByPk(3);
		$this->assertEquals(1,$post->primaryKey);
		$this->assertTrue($post->equals($post2));
		$this->assertTrue($post2->equals($post));
		$this->assertFalse($post->equals($post3));
		$this->assertFalse($post3->equals($post));
	}

	public function testValidation()
	{
		$user=new User;
		$user->password='passtest';
		$this->assertFalse($user->hasErrors());
		$this->assertEquals(array(),$user->errors);
		$this->assertEquals(array(),$user->getErrors('username'));
		$this->assertFalse($user->save());
		$this->assertNull($user->id);
		$this->assertTrue($user->isNewRecord);
		$this->assertTrue($user->hasErrors());
		$this->assertTrue($user->hasErrors('username'));
		$this->assertTrue($user->hasErrors('email'));
		$this->assertFalse($user->hasErrors('password'));
		$this->assertEquals(1,count($user->getErrors('username')));
		$this->assertEquals(1,count($user->getErrors('email')));
		$this->assertEquals(2,count($user->errors));

		$user->clearErrors();
		$this->assertFalse($user->hasErrors());
		$this->assertEquals(array(),$user->errors);
	}

	public function testCompositeKey()
	{
		$order=new Order;
		$this->assertEquals(array('key1','key2'),$order->tableSchema->primaryKey);
		$order=Order::model()->findByPk(array('key1'=>2,'key2'=>1));
		$this->assertEquals('order 21',$order->name);
		$orders=Order::model()->findAllByPk(array(array('key1'=>2,'key2'=>1),array('key1'=>1,'key2'=>3)));
		$this->assertEquals('order 13',$orders[0]->name);
		$this->assertEquals('order 21',$orders[1]->name);
	}

	public function testDefault()
	{
		$type=new ComplexType;
		$this->assertEquals(1,$type->int_col2);
		$this->assertEquals('something',$type->char_col2);
		$this->assertEquals(1.23,$type->float_col2);
		$this->assertEquals(33.22,$type->numeric_col);
		$this->assertEquals(123,$type->time);
		$this->assertEquals(null,$type->bool_col);
		$this->assertEquals(true,$type->bool_col2);
	}

	public function testPublicAttribute()
	{
		$post=new PostExt;
		$this->assertEquals(array('id'=>null,'title'=>'default title','create_time'=>null,'author_id'=>null,'content'=>null),$post->attributes);
		$post=Post::model()->findByPk(1);
		$this->assertEquals(array(
			'id'=>1,
			'title'=>'post 1',
			'create_time'=>100000,
			'author_id'=>1,
			'content'=>'content 1'),$post->attributes);

		$post=new PostExt;
		$post->title='test post';
		$post->create_time=1000000;
		$post->author_id=1;
		$post->content='test';
		$post->save();
		$this->assertEquals(array(
			'id'=>6,
			'title'=>'test post',
			'create_time'=>1000000,
			'author_id'=>1,
			'content'=>'test'),$post->attributes);
	}

	public function testLazyRelation()
	{
		// test belongsTo
		$post=Post::model()->findByPk(2);
		$this->assertTrue($post->author instanceof User);
		$this->assertEquals(array(
			'id'=>2,
			'username'=>'user2',
			'password'=>'pass2',
			'email'=>'email2'),$post->author->attributes);

		// test hasOne
		$post=Post::model()->findByPk(2);
		$this->assertTrue($post->firstComment instanceof Comment);
		$this->assertEquals(array(
			'id'=>4,
			'content'=>'comment 4',
			'post_id'=>2,
			'author_id'=>2),$post->firstComment->attributes);
		$post=Post::model()->findByPk(4);
		$this->assertNull($post->firstComment);

		// test hasMany
		$post=Post::model()->findByPk(2);
		$this->assertEquals(2,count($post->comments));
		$this->assertEquals(array(
			'id'=>5,
			'content'=>'comment 5',
			'post_id'=>2,
			'author_id'=>2),$post->comments[0]->attributes);
		$this->assertEquals(array(
			'id'=>4,
			'content'=>'comment 4',
			'post_id'=>2,
			'author_id'=>2),$post->comments[1]->attributes);
		$post=Post::model()->findByPk(4);
		$this->assertEquals(array(),$post->comments);

		// test manyMany
		$post=Post::model()->findByPk(2);
		$this->assertEquals(2,count($post->categories));
		$this->assertEquals(array(
			'id'=>4,
			'name'=>'cat 4',
			'parent_id'=>1),$post->categories[0]->attributes);
		$this->assertEquals(array(
			'id'=>1,
			'name'=>'cat 1',
			'parent_id'=>null),$post->categories[1]->attributes);
		$post=Post::model()->findByPk(4);
		$this->assertEquals(array(),$post->categories);

		// test self join
		$category=Category::model()->findByPk(5);
		$this->assertEquals(array(),$category->posts);
		$this->assertEquals(2,count($category->children));
		$this->assertEquals(array(
			'id'=>6,
			'name'=>'cat 6',
			'parent_id'=>5),$category->children[0]->attributes);
		$this->assertEquals(array(
			'id'=>7,
			'name'=>'cat 7',
			'parent_id'=>5),$category->children[1]->attributes);
		$this->assertTrue($category->parent instanceof Category);
		$this->assertEquals(array(
			'id'=>1,
			'name'=>'cat 1',
			'parent_id'=>null),$category->parent->attributes);

		$category=Category::model()->findByPk(2);
		$this->assertEquals(1,count($category->posts));
		$this->assertEquals(array(),$category->children);
		$this->assertNull($category->parent);

		// test composite key
		$order=Order::model()->findByPk(array('key1'=>1,'key2'=>2));
		$this->assertEquals(2,count($order->items));
		$order=Order::model()->findByPk(array('key1'=>2,'key2'=>1));
		$this->assertEquals(0,count($order->items));
		$item=Item::model()->findByPk(4);
		$this->assertTrue($item->order instanceof Order);
		$this->assertEquals(array(
			'key1'=>2,
			'key2'=>2,
			'name'=>'order 22'),$item->order->attributes);
	}

	public function testEagerRelation()
	{
		$post=Post::model()->with('author','firstComment','comments','categories')->findByPk(2);
		$this->assertEquals(array(
			'id'=>2,
			'username'=>'user2',
			'password'=>'pass2',
			'email'=>'email2'),$post->author->attributes);
		$this->assertTrue($post->firstComment instanceof Comment);
		$this->assertEquals(array(
			'id'=>4,
			'content'=>'comment 4',
			'post_id'=>2,
			'author_id'=>2),$post->firstComment->attributes);
		$this->assertEquals(2,count($post->comments));
		$this->assertEquals(array(
			'id'=>5,
			'content'=>'comment 5',
			'post_id'=>2,
			'author_id'=>2),$post->comments[0]->attributes);
		$this->assertEquals(array(
			'id'=>4,
			'content'=>'comment 4',
			'post_id'=>2,
			'author_id'=>2),$post->comments[1]->attributes);
		$this->assertEquals(2,count($post->categories));
		$this->assertEquals(array(
			'id'=>4,
			'name'=>'cat 4',
			'parent_id'=>1),$post->categories[0]->attributes);
		$this->assertEquals(array(
			'id'=>1,
			'name'=>'cat 1',
			'parent_id'=>null),$post->categories[1]->attributes);

		$post=Post::model()->with('author','firstComment','comments','categories')->findByPk(4);
		$this->assertEquals(array(
			'id'=>2,
			'username'=>'user2',
			'password'=>'pass2',
			'email'=>'email2'),$post->author->attributes);
		$this->assertNull($post->firstComment);
		$this->assertEquals(array(),$post->comments);
		$this->assertEquals(array(),$post->categories);
	}

	public function testLazyRecursiveRelation()
	{
		$post=PostExt::model()->findByPk(2);
		$this->assertEquals(2,count($post->comments));
		$this->assertTrue($post->comments[0]->post instanceof Post);
		$this->assertTrue($post->comments[1]->post instanceof Post);
		$this->assertTrue($post->comments[0]->author instanceof User);
		$this->assertTrue($post->comments[1]->author instanceof User);
		$this->assertEquals(3,count($post->comments[0]->author->posts));
		$this->assertEquals(3,count($post->comments[1]->author->posts));
		$this->assertTrue($post->comments[0]->author->posts[1]->author instanceof User);

		// test self join
		$category=Category::model()->findByPk(1);
		$this->assertEquals(2,count($category->nodes));
		$this->assertTrue($category->nodes[0]->parent instanceof Category);
		$this->assertTrue($category->nodes[1]->parent instanceof Category);
		$this->assertEquals(0,count($category->nodes[0]->children));
		$this->assertEquals(2,count($category->nodes[1]->children));
	}

	public function testEagerRecursiveRelation()
	{
		$post=Post::model()->with(array('comments'=>'author','categories'))->findByPk(2);
		$this->assertEquals(2,count($post->comments));
		$this->assertEquals(2,count($post->categories));
	}

	public function testRelationWithCondition()
	{
		$posts=Post::model()->with('comments')->findAllByPk(array(2,3,4));
		$this->assertEquals(3,count($posts));
		$this->assertEquals(2,count($posts[0]->comments));
		$this->assertEquals(4,count($posts[1]->comments));
		$this->assertEquals(0,count($posts[2]->comments));

		$post=Post::model()->with('comments')->findByAttributes(array('id'=>2));
		$this->assertTrue($post instanceof Post);
		$this->assertEquals(2,count($post->comments));
		$posts=Post::model()->with('comments')->findAllByAttributes(array('id'=>2));
		$this->assertEquals(1,count($posts));

		$post=Post::model()->with('comments')->findBySql('select * from posts where id=:id',array(':id'=>2));
		$this->assertTrue($post instanceof Post);
		$posts=Post::model()->with('comments')->findAllBySql('select * from posts where id=:id1 OR id=:id2',array(':id1'=>2,':id2'=>3));
		$this->assertEquals(2,count($posts));

		$post=Post::model()->with('comments','author')->find('posts.id=:id',array(':id'=>2));
		$this->assertTrue($post instanceof Post);

		$posts=Post::model()->with('comments','author')->findAll(array(
			'select'=>'title',
			'condition'=>'posts.id=:id',
			'limit'=>1,
			'offset'=>1,
			'order'=>'posts.title',
			'group'=>'posts.id',
			'params'=>array(':id'=>2)));
		$this->assertTrue($posts[0] instanceof Post);
	}
}
