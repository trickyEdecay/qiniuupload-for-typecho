<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 七牛文章上传图片/文件功能
 *
 * @package QiniuPostUploader
 * @author  trickyedecay
 * @version 0.1
 * @link http://www.trickyedecay.me
 */
class QiniuPostUploader_Plugin implements Typecho_Plugin_Interface
{

    public static function activate(){
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array("QiniuPostUploader_Plugin","render");
        return "插件启用成功!请设置相关属性";
    }

    public static function deactivate(){
        return "插件已成功禁用!";
    }

    public static function config(Typecho_Widget_Helper_Form $form){
        $bucketname_element = new Typecho_Widget_Helper_Form_Element_Text('bucketname', null, null, _t('bucket名称(空间名称)'), '可以到七牛后台新建/获取');
        $domain_element = new Typecho_Widget_Helper_Form_Element_Text('domain', null, null, _t('空间域名'), '与bucket绑定的域名');
        $accesskey_element = new Typecho_Widget_Helper_Form_Element_Text('accesskey', null, null, _t('Accesskey'), '可以到七牛个人面板->秘钥管理获取');
        $secretkey_element = new Typecho_Widget_Helper_Form_Element_Text('secretkey', null, null, _t('Secretkey'), '可以到七牛个人面板->秘钥管理获取');
        $form->addInput($bucketname_element);
        $form->addInput($domain_element);
        $form->addInput($accesskey_element);
        $form->addInput($secretkey_element);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    public static function render($post){
        //插件目录
        $urlbase = Helper::options()->pluginUrl . '/QiniuPostUploader/';
        $cssUrl = $urlbase . 'src/main.css';
        echo '<link rel="stylesheet" type="text/css" href="' . $cssUrl . '" />';
        echo '<script src="//cdn.bootcss.com/plupload/2.1.8/moxie.min.js"></script>';
        echo '<script src="//cdn.bootcss.com/plupload/2.1.8/plupload.full.min.js"></script>';
        echo '<script src="//cdn.bootcss.com/qiniu-js/1.0.17.1/qiniu.js"></script>';
        echo '<link href="//cdn.bootcss.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">';
        echo '<script src="//cdn.bootcss.com/zeroclipboard/2.2.0/ZeroClipboard.min.js"></script>';
        ?>
    <script>
    $(function(){
        var $container = $("<section id='trickyqiniu_container'></section>");
        $("#wmd-editarea").after($container);
        
        $container.load("<?php echo $urlbase;?>src/main.html",function(){
            var domain = '<?php echo Helper::options()->plugin('QiniuPostUploader')->domain;?>';
            var filecount = 0;
            var trickyqiniu_uploader = Qiniu.uploader({
                runtimes: 'html5,flash,html4',      // 上传模式,依次退化
                browse_button: 'trickyqiniu_pickfiles',         // 上传选择的点选按钮，**必需**
                uptoken_url: '<?php echo $urlbase."src/"?>uptoken.php',         // Ajax 请求 uptoken 的 Url，**强烈建议设置**（服务端提供）
                get_new_uptoken: true,             // 设置上传文件的时候是否每次都重新获取新的 uptoken
                // unique_names: true,              // 默认 false，key 为文件名。若开启该选项，JS-SDK 会为每个文件自动生成key（文件名）
                // save_key: true,                  // 默认 false。若在服务端生成 uptoken 的上传策略中指定了 `sava_key`，则开启，SDK在前端将不对key进行任何处理
                domain: domain,     // bucket 域名，下载资源时用到，**必需**
                container: 'trickyqiniu_droparea',             // 上传区域 DOM ID，默认是 browser_button 的父元素，
                max_file_size: '100mb',             // 最大文件体积限制
                flash_swf_url: 'http://cdn.bootcss.com/plupload/2.1.9/Moxie.swf',  //引入 flash,相对路径
                max_retries: 3,                     // 上传失败最大重试次数
                dragdrop: false,                     // 开启可拖曳上传
                drop_element: 'trickyqiniu_droparea',          // 拖曳上传区域元素的 ID，拖曳文件或文件夹后可触发上传
                chunk_size: '4mb',                  // 分块上传时，每块的体积
                auto_start: true,                   // 选择文件后自动上传，若关闭需要自己绑定事件触发上传,
                init: {
                    'FilesAdded': function(up, files) {
                        plupload.each(files, function(file) {
                            // 文件添加进队列后,处理相关的事情
                            console.log(JSON.stringify(file));
                        });
                    },
                    'BeforeUpload': function(up, file) {
                           // 每个文件上传前,处理相关的事情
                    },
                    'UploadProgress': function(up, file) {
                           // 每个文件上传时,处理相关的事情
                    },
                    'FileUploaded': function(up, file, info) {
                       // 每个文件上传成功后,处理相关的事情
                       // 其中 info 是文件上传成功后，服务端返回的json，形式如
                       // {
                       //    "hash": "Fh8xVqod2MQ1mocfI4S4KpRL6D98",
                       //    "key": "gogopher.jpg"
                       //  }
                       // 参考http://developer.qiniu.com/docs/v6/api/overview/up/response/simple-response.html

                        var domain = up.getOption('domain');
                        var res = JSON.parse(info);
                        var sourceLink = domain +"/"+ res.key; //获取上传成功后的文件的Url
                        var mime = file.type;
                        filecount++;
                        
                        var markdownlink = "![]("+sourceLink+")";
                        //如果是图片,则添加以下节点
                        if(mime.split("/")[0]=="image"){
                            var item = "<div class='trickyqiniu_itemcontainer'>"+
                                            "<img alt='' src='"+sourceLink+"'>" +
                                            "<ul>" +
                                                "<li><p class='trickyqiniu_filename'>"+res.key+"</p></li>" +
                                                "<li><a class='trickyqiniu_link' href='"+sourceLink+"' target='_blank'><i class='fa fa-external-link-square' aria-hidden='true'></i>&nbsp;&nbsp;"+sourceLink+"</a></li>" +
                                                "<li><button type='button' data-clipboard-text=\""+markdownlink+"\" id=\"copybtn-"+filecount+"\"><i class='fa fa-link' aria-hidden='true'></i>&nbsp;&nbsp;复制markdown</button></li>" +
                                            "</ul>" +
                                        "</div>";
                            
                            $("#trickyqiniu_items").append(item);
                            
                            var client = new ZeroClipboard( document.getElementById("copybtn-"+filecount) );

                            client.on( "ready", function( readyEvent ) {
                              client.on( "aftercopy", function( event ) {
                              });
                            });
                        }
                        console.log(sourceLink);
                    },
                    'Error': function(up, err, errTip) {
                           //上传出错时,处理相关的事情
                        alert(JSON.stringify(err));
                    },
                    'UploadComplete': function() {
                           //队列文件处理完毕后,处理相关的事情
                    },
                    'Key': function(up, file) {
                        // 若想在前端对每个文件的key进行个性化处理，可以配置该函数
                        // 该配置必须要在 unique_names: false , save_key: false 时才生效
                        var date = new Date();
                        var time = date.getFullYear()+""+date.getMonth()+""+date.getDay();
                        var key = time+randomWord(false,5)+"_"+file.name;
                        // do something with key here
                        return key
                    }
                }
            });
            
        });
        
        //生成随机字符
        function randomWord(randomFlag, min, max){
            var str = "",
                range = min,
                arr = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

            // 随机产生
            if(randomFlag){
                range = Math.round(Math.random() * (max-min)) + min;
            }
            for(var i=0; i<range; i++){
                pos = Math.round(Math.random() * (arr.length-1));
                str += arr[pos];
            }
            return str;
        }
    });
        
        //复制
        function copylink(link,eleid){
//            ZeroClipboard.config( { swfPath: 'http://cdn.bootcss.com/zeroclipboard/2.2.0/ZeroClipboard.swf' } );
            
        }
    </script>
<?php
    }
}

?>