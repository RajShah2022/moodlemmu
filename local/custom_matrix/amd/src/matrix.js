define([
        'jquery', 
        'core/str',         
        'core/modal_factory', 
        'core/modal_events', 
        'core/fragment', 
        'core/ajax', 
        'core/templates',
        'core/yui',
        'core/loadingicon'
        ],
function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Templates, Y, LoadingIcon) {

    var matrixcategories = function(ccID,roleID,tempID){ 
        var loadElement = $('.loadElement');
        var loadingIcon = LoadingIcon.addIconToContainerWithPromise(loadElement);      
        var params = {};                            
        params.costcenterid = ccID;        
        params.templateid = tempID;        
        params.role = (roleID != undefined)? roleID : 0;        
        var promise = Ajax.call([{
            methodname: 'local_custom_matrix_matrix_view',
            args: params
        }]);
            
        promise[0].done(function(resp) {                        
            loadingIcon.resolve();
            var show = (resp['pm_records_count'] > 0)?false:true;
            var data = Templates.render('local_custom_matrix/matrix_view'+name, {response: resp['records'],show : show});
            data.then(function(response){
                $("#fgroup_id_matrix").html(response);
            });
        }).fail(function(ex) {
            loadingIcon.resolve();
            // do something with the exception
            console.log(ex);
        }); 
    };

return {
        fetchMatrixCategories: function(roletype,orgid,tempID){            
            if(roletype != 0){
                $('#id_role').on('change',function(){                 
                    var roleID = $(this).val();                                
                    var ccID = (orgid != 0)?orgid :$('#id_costcenter_id').val();
                    matrixcategories(ccID,roleID,tempID); 
                });
            }else{                
                if(orgid != 0){
                    var roleID = 0;                                
                    var ccID = orgid; 
                    matrixcategories(ccID,roleID,tempID);
                }else{
                    $('#id_costcenter_id').on('change',function(){    
                        var roleID = 0;                                
                        var ccID = $(this).val(); 
                        matrixcategories(ccID,roleID,tempID); 
                    });
                }
                
            }                
        },
        fetchuserMatrixdata: function(){  
            var loadElement = $('.loadElement');
            var loadingIcon = LoadingIcon.addIconToContainerWithPromise(loadElement);          
            var params = {};
            params.period = $('#perforamce_period').val();
            params.userid = $('#userid').val();
            params.orgid = $('option:selected', '#perforamce_period').attr('data-org');
            params.role =  $('option:selected', '#perforamce_period').attr('data-role');
            params.year =  $('option:selected', '#perforamce_period').attr('data-year');
            params.year = (params.year=='')?0:params.year;
            params.month = $('option:selected', '#perforamce_period').attr('data-month');
            params.tempid = $('option:selected', '#perforamce_period').attr('data-tempid');
            if(params.period != ''){
                $('#heading_id').html($('option:selected', '#perforamce_period').attr('data-name')); 
            }else{
                $('#heading_id').html($('#heading_old').val()); 
            }           
            var promise = Ajax.call([{
                methodname: 'local_custom_matrix_user_matrix_view',
                args: params
            }]);

            promise[0].done(function(resp) {  
                var response = resp['records'];
                var html = '';
                response.forEach((value,key) => {

                    if(value.parentid==0){
                        html += '<tr>';
                        html += '<td class="text-left" ><h6>'+value.fullname+'';
                        html += '<div class="input-group">';
                        html += '<input name="data_'+value.id+'_performancetype" type="hidden" value="'+value.fullname+'" id="performancetype_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_id" type="hidden" value="'+value.id+'" id="parentid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_poid" type="hidden" value="'+value.poid+'" id="poid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_role" type="hidden" value="'+value.role+'" id="role_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_parentid" type="hidden" value="'+value.parentid+'" id="parentid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_type" type="hidden" value="'+value.type+'" id="type_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_logid" type="hidden" value="'+value.logid+'" id="logid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_templateid" type="hidden" value="'+value.templateid+'" id="templateid_'+value.id+'">';
                        html += ' </div>';
                        html +='</h6></td>';
                        html += '<td></td>';
                        html += '<td></td>';
                        html += '<td><h6>'+value.weightage+'</h6>';
                        html += '<input type="hidden" class="form-control " name="data_'+value.id+'_weight" id="weight_'+value.id+'" value="'+value.weightage+'">';
                        html += '</td>';
                        html += '<td></td>';                        
                        html += '</tr>';
                    }else{
                        html += '<tr>';
                        html += '<td></td>';
                        html += '<td class="text-left" ><h6>'+value.fullname+'';
                        html += '<div class="input-group">';
                        html += '<input name="data_'+value.id+'_performancetype" type="hidden" value="'+value.fullname+'" id="performancetype_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_id" type="hidden" value="'+value.id+'" id="parentid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_poid" type="hidden" value="'+value.poid+'" id="poid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_role" type="hidden" value="'+value.role+'" id="role_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_parentid" type="hidden" value="'+value.parentid+'" id="parentid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_type" type="hidden" value="'+value.type+'" id="type_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_logid" type="hidden" value="'+value.logid+'" id="logid_'+value.id+'">';
                        html += '<input name="data_'+value.id+'_templateid" type="hidden" value="'+value.templateid+'" id="templateid_'+value.id+'">';
                        html += '</div>';
                        html +='</h6></td>';
                        html += '<td><h6>'+value.maxscore+'</h6>';
                        html += ' <input type="hidden" class="form-control " name="data_'+value.id+'_maxscore" id="maxscore_'+value.id+'" value="'+value.maxscore+'">';
                        html += '</td>';
                        html += '<td></td>';
                        if(value.type ==1){
                            html +='<td class="text-center">';
                            html +='<div class="form-group  fitem  ">';
                            html +='<span data-fieldtype="text">';
                            html +='<input type="number" class="form-control" style="width:40%;" name="data_'+value.id+'_userscore" id="userscore_'+value.id+'" value="'+value.userscore+'">';
                            html +='</span>';
                            html +='<div class="form-control-feedback invalid-feedback" id="userscore_error_'+value.id+'">';
                            html +=' </div>';
                            html +='</div>';
                            html +='</td>';

                        }else{
                            if(value.userscore){
                                html += '<td class="text-left"><h6>'+value.userscore+'</h6></td>'; 
                            }else{
                                html += '<td></td>'; 
                            }   
                            
                        }
                        
                        html += '</tr>';
                    }         

                });
                $("#user_matrix_tbody").html(html);
                loadingIcon.resolve();
            }).fail(function(ex) {
                loadingIcon.resolve();
                // do something with the exception
                console.log(ex);
            }); 
        },
        saveMatrixdata: function(){  
            var loadElement = $('.loadElement');
            var loadingIcon = LoadingIcon.addIconToContainerWithPromise(loadElement);
            var formData = $('form').serializeArray();
            var formObj = {};
            $.each(formData, function (i, input) {
                formObj[input.name] = input.value;
            });               

            const keys = Object.keys(formObj);
            var objval = [];
            
            keys.forEach((key, index) => {                 
                 var str = key;
                 var arr = str.split('_');                                          
                 if(arr[0]== 'data'){                   

                    objval[arr[1]] = [{
                        'check':formObj['data_'+arr[1]+'_check'],
                        'performancecatid': formObj['data_'+arr[1]+'_id'],
                        'parentid': formObj['data_'+arr[1]+'_parentid'],
                        'performancetype': formObj['data_'+arr[1]+'_performancetype'],
                        'maxscore': formObj['data_'+arr[1]+'_maxscore'],
                        'weightage': formObj['data_'+arr[1]+'_weight'],
                        'path': formObj['data_'+arr[1]+'_path'],
                        'type': formObj['data_'+arr[1]+'_type'],
                        'templateid': $("input[name=tempid]").val(),
                        'role':formObj['role'],
                        'id':(formObj['data_'+arr[1]+'_pmid'] == '')? 0 :formObj['data_'+arr[1]+'_pmid'],
                    }];        
                 }                      
            });
           
            var finalObj = [];
            objval.forEach((value,key) => {                  
                finalObj.push(objval[key][0]);
            });
            
            var perfoParams = [];
            var totalWeightage = 0;
            var checkErrors = 0;
            var perfoparentid = 0;
            var checkParentCount = 0;
            var checkChildCount = 0;
            finalObj.forEach((value,key) => {
                
                if(value.parentid == 0 ){

                    if( value.check == 1){
                        checkParentCount++; 
                        perfoparentid = value.performancecatid;
                    }
                        
                    if(value.weightage == '' && value.check == 1){                              
                        checkErrors = 1;                       
                        $('#weight_error_'+value['performancecatid']).text('Please enter valid weightage');
                        $('#weight_error_'+value['performancecatid']).css('display','block');  
                        $('#weight_'+value['performancecatid']).addClass('is-invalid');  
                    }else{  
                         
                        $('#weight_error_'+value['performancecatid']).css('display','none');
                        $('#weight_'+value['performancecatid']).removeClass('is-invalid'); 
                    }
                }

                if(value.parentid != 0){ 
                    if(value.check == 1){
                        checkChildCount++;
                    }                      
                    if(value.maxscore == '' && value.check == 1){
                        checkErrors = 1;                            
                        $('#maxscore_error_'+value['performancecatid']).text('Please enter valid maxscore');
                        $('#maxscore_error_'+value['performancecatid']).css('display','block');  
                        $('#maxscore_'+value['performancecatid']).addClass('is-invalid');  
                    }else{
                        
                        $('#maxscore_error_'+value['performancecatid']).css('display','none');
                        $('#maxscore_'+value['performancecatid']).removeClass('is-invalid'); 
                    }
                }
                
                if(value['weightage'] != undefined && value.check == 1){
                    totalWeightage = parseInt(value['weightage'])+parseInt(totalWeightage);                     
                }
            
                if(value['parentid'] != 0 && value.check == 1){  
                    var  parentId =  (value['parentid'] == -1)? 0 :value['parentid'];              
                    if(parentId in perfoParams){
                        perfoParams[parentId].push({'maxscore': value['maxscore'],'id': value['performancecatid']});
                    }else{
                       perfoParams[parentId] = [{'maxscore': value['maxscore'],'id': value['performancecatid']}, 
                                ]; 
                   }  
                }  
            });                      
            var childid = 0;
            perfoParams.forEach((pervalue,perkey) => {
                var subparamTotal = 0;                   
               
                pervalue.forEach((val,key) => {
                    subparamTotal = parseInt(subparamTotal) +parseInt(val['maxscore']);
                    childid = val['id'];                        
                });
              
                if((subparamTotal > 100) || (subparamTotal < 100)){
                    checkErrors = 1; 
                    $('#maxscore_error_'+childid).text('Sum of Performace params maxscore should be 100');
                    $('#maxscore_error_'+childid).css('display','block');  
                }
              
            });            
            totalWeightage = isNaN(totalWeightage)? 0 : totalWeightage;           
            if(totalWeightage == 0){
                checkErrors = 1;  
            }else{
                if((totalWeightage > 100) || (totalWeightage < 100)){                   
                    checkErrors = 1;                                     
                    $('#weight_error_'+perfoparentid).text('Sum of Performace params weightage should be 100');
                    $('#weight_error_'+perfoparentid).css('display','block');  
                }else{                  
                    $('#weight_error_'+perfoparentid).css('display','none');
                    $('#weight_'+perfoparentid).removeClass('is-invalid'); 
                }
            }                       

            var tFinalObj = [];
            finalObj.forEach((value,key) => {
                value.parentid = (value.parentid == -1)? 0:value.parentid;
                if(value.check == 1){
                    tFinalObj.push(value);
                }
            });
         
            if(checkErrors == 0){
                var params = {}; 
                params.jsonformdata = JSON.stringify(tFinalObj);                
                var promise = Ajax.call([{
                  methodname: 'local_custom_matrix_matrix_save',
                  args: params,
                }]);

                Str.get_strings([{
                    key: 'ok',
                    component: 'local_custom_matrix',
                }]).then(function(s) {
                    promise[0].done(function(returndata) { 
                    var response = returndata;                                     
                    loadingIcon.resolve();
                    ModalFactory.create({
                    title: 'Message',
                    type: ModalFactory.types.DEFAULT,
                    body: response.message,
                    footer: '<button type="button" class="btn btn-secondary" data-action="cancel">'+s[0]+'</button>'
                        }).done(function(modal) {
                            this.modal = modal;                          
                            modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                                modal.setBody('');
                                modal.hide();
                                var catid =  $("input[name=orgid]").val();
                                var role =  $('#id_role').val();
                                var tempid =  $("input[name=tempid]").val();                           
                                matrixcategories(catid,role,tempid);
                            });
                            modal.show();
                        }.bind(this));

                    }).fail(function(ex) {
                        loadingIcon.resolve();
                        // do something with the exception
                        console.log(ex);
                    });

                }.bind(this));

            }else{
                loadingIcon.resolve();                             
                if(checkParentCount == 0 || checkChildCount == 0){
                    ModalFactory.create({
                    title: 'Message',
                    type: ModalFactory.types.DEFAULT,
                    body: 'Need to check atleast one Performance Category and Sub Category',
                    footer: '<button type="button" class="btn btn-secondary" data-action="cancel">Ok</button>'
                        }).done(function(modal) {
                        this.modal = modal;                          
                        modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                            modal.setBody('');
                            modal.hide();                          
                        });
                        modal.show();
                    }.bind(this));
                }
            }             
        },
        saveUserMatrixdata: function(){
            var loadElement = $('.loadElement');
            var loadingIcon = LoadingIcon.addIconToContainerWithPromise(loadElement);
            var formData = $('form').serializeArray();
            var formObj = {};               
            $.each(formData, function (i, input) {
                formObj[input.name] = input.value;
            });           
            const keys = Object.keys(formObj);
            var objval = []; 
            keys.forEach((key, index) => {  
                var str = key;
                var arr = str.split('_');                     
                if(arr[0]== 'data'){    
                    if(formObj['data_'+arr[1]+'_userscore']){
                        objval[arr[1]] = [{
                        'userid':formObj['userid'],
                        'performancetype': formObj['data_'+arr[1]+'_performancetype'],
                        'performancecatid': formObj['data_'+arr[1]+'_id'],
                        'maxpoints': formObj['data_'+arr[1]+'_maxscore'],
                        'totalpoints':formObj['data_'+arr[1]+'_userscore'],
                        'weightage': formObj['data_'+arr[1]+'_weight'],
                        'role':formObj['data_'+arr[1]+'_role'],
                        'parentid':formObj['data_'+arr[1]+'_parentid'],
                        'type':formObj['data_'+arr[1]+'_type'],
                        'templateid':formObj['data_'+arr[1]+'_templateid'],
                        'id':(formObj['data_'+arr[1]+'_poid'] == '')? 0 :formObj['data_'+arr[1]+'_poid'],
                        'logid':(formObj['data_'+arr[1]+'_logid'] == '')? 0 :formObj['data_'+arr[1]+'_logid'],
                        
                        }];
                    }       
                }                      
            });

            var finalObj = [];
            objval.forEach((value,key) => {                  
                finalObj.push(objval[key][0]);
            });             
            var checkErrors = 0; 
            finalObj.forEach((value,key) => {               
                    
                    if(value.parentid != 0){                       
                        if(value.totalpoints == ''){
                            checkErrors = 1;                            
                            $('#userscore_error_'+value['performancecatid']).text('Please enter valid maxscore');
                            $('#userscore_error_'+value['performancecatid']).css('display','block');  
                            $('#userscore_'+value['performancecatid']).addClass('is-invalid');  
                        }else{
                            $('#userscore_error_'+value['performancecatid']).css('display','none');
                            $('#userscore_'+value['performancecatid']).removeClass('is-invalid'); 
                        }
                        if(value.totalpoints != '' && value.totalpoints != undefined){
                            var totalpoit = parseInt(value.totalpoints);
                            var maxpoit = parseInt(value.maxpoints);
                            if(totalpoit > maxpoit){
                                checkErrors = 1;   
                                $('#userscore_error_'+value['performancecatid']).text('User Score should be less than or equal to Max Score');
                                $('#userscore_error_'+value['performancecatid']).css('display','block'); 
                                $('#userscore_'+value['performancecatid']).addClass('is-invalid'); 
                            }else{
                                $('#userscore_error_'+value['performancecatid']).css('display','none');
                                $('#userscore_'+value['performancecatid']).removeClass('is-invalid');
                            }
                        }
                    }
                });

            finalObj.forEach((value,key) => {
                value.parentid = (value.parentid == -1)? 0:value.parentid;
            });
            if(checkErrors == 0){
                var params = {}; 
                params.jsonformdata = JSON.stringify(finalObj);                
                var promise = Ajax.call([{
                  methodname: 'local_custom_matrix_user_matrix_save',
                  args: params
                }]);
                Str.get_strings([{
                    key: 'ok',
                    component: 'local_custom_matrix',
                }]).then(function(s) {
                    promise[0].done(function(returndata) {                  
                    var response = returndata;
                    loadingIcon.resolve();                    
                    ModalFactory.create({
                    title: 'Message',
                    type: ModalFactory.types.DEFAULT,
                    body: response.message,
                    footer: '<button type="button" class="btn btn-secondary" data-action="cancel">'+s[0]+'</button>'
                        }).done(function(modal) {
                            this.modal = modal;                          
                            modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                                modal.setBody('');
                                modal.hide();
                                location.reload();                               
                            });
                            modal.show();
                        }.bind(this));
                    }).fail(function(ex) {
                        loadingIcon.resolve();
                        // do something with the exception
                        console.log(ex);
                    });
                 }.bind(this));
            }  
        },
        cancelMatrix: function(){
            window.reload;
            $("#id_costcenter_id").val('');
            $("#id_role").val('');
        },
        fetchEndUserMatrix: function(){
            var loadElement = $('.loadElement');
            var loadingIcon = LoadingIcon.addIconToContainerWithPromise(loadElement);
            var params = {};           
            params.period = $('#perforamce_period').val();
            params.userid = $('#userid').val();
            params.orgid = $('option:selected', '#perforamce_period').attr('data-org');
            params.role =  $('option:selected', '#perforamce_period').attr('data-role');
            params.year =  $('option:selected', '#perforamce_period').attr('data-year');
            params.month = $('option:selected', '#perforamce_period').attr('data-month');
            params.tempid = $('option:selected', '#perforamce_period').attr('data-tempid');
            params.year = (params.year=='')?0:params.year;
            params.orgid = (params.orgid=='')?$('#orgid').val():params.orgid;

            if(params.period != ''){
                $('#heading_id').html($('option:selected', '#perforamce_period').attr('data-name')); 
            }else{
                $('#heading_id').html($('#heading_old').val()); 
            }           
            var promise = Ajax.call([{
                methodname: 'local_custom_matrix_user_matrix_view',
                args: params
            }]); 
            promise[0].done(function(resp) {  
                var response = resp['records'];               
                var html = '';
                response.forEach((value,key) => {

                    if(value.parentid==0){
                        html += '<tr>';
                        html += '<td class="text-left" ><h6>'+value.fullname+'</h6>';                        
                        html +='</td>';
                        html += '<td></td>';
                        html += '<td></td>';
                        html += '<td><h6>'+value.weightage+'</h6>';                       
                        html += '</td>';
                        html += '<td></td>';   
                        html += '</tr>';
                    }else{
                        html += '<tr>';
                        html += '<td></td>';
                        html += '<td class="text-left" ><h6>'+value.fullname+'</h6>';                        
                        html +='</td>';
                        html += '<td><h6>'+value.maxscore+'</h6>';
                        html += '</td>';
                        html += '<td></td>';
                        if(value.type ==1){
                            if(value.userscore){
                                html += '<td class="text-center"><h6>'+value.userscore+'</h6></td>'; 
                            }else{
                                html += '<td></td>'; 
                            }    
                           
                        }else{                            
                            if(value.userscore){
                                html += '<td class="text-center"><h6>'+value.userscore+'</h6></td>'; 
                            }else{
                                html += '<td></td>'; 
                            }                              
                        }                        
                        html += '</tr>';
                    }
               
                });
                 $("#matrix_tbody").html(html);
                loadingIcon.resolve();
            }).fail(function(ex) {
                    // do something with the exception
                    console.log(ex);
            });     

    },


};

});
