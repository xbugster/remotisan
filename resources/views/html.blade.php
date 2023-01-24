<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space%20Mono%3Aital%2Cwght%400%2C400&directory=3&display=block">

<div class="container" id="container" data-ng-app="RemotisanApp" data-ng-controller="RemotisanController">
    <h2>Commands</h2>
    <form class="form-inline" data-ng-submit="execute()" data-ng-init='init("{{ config('remotisan.url') }}")'>
        <label class="my-1 mr-2" for="inlineFormCustomSelectPref">Preference</label>
        <select required class="custom-select my-1 mr-sm-2" data-ng-model="command" name="command"
                data-ng-options='c.name as (c.name + " - " + c.description) for c in commands' data-ng-change="onChangeDropdownValue()">
        </select>

        <textarea placeholder="input options & arguments (if required)..." name="params" data-ng-model="params" style="width:70%"></textarea>

        <input type="button" class="btn btn-primary" data-ng-click="execute()" value="Execute" />

        <hr style="opacity:0; display:block; width:100%;"/>

        <div data-ng-show="command">
            <div class="abc" style="background-color: #f9fdf0">
                <div><strong>Command name:</strong> @{{commands[command].name}}</div>
                <div><strong>Description:</strong> @{{commands[command].description}}</div>
                <div><strong>Help:</strong> @{{commands[command].help}}</div>
                <div><strong>Arguments:</strong></div>
                <div style="margin-left:20px;" data-ng-repeat="(field_name, field_details) in commands[command]['definition']['args']">
                    <div><strong>@{{field_name}}:</strong> @{{field_details}}</div>
                </div>
                <div><strong>Options:</strong></div>
                <div style="margin-left:20px;" data-ng-repeat="(field_name, field_details) in commands[command]['definition']['ops']">
                    <div><strong>@{{field_name}}:</strong> @{{field_details}}</div>
                </div>
            </div>
        </div>
    </form>

    <div class="history-wrapper"> <!-- show when history button clicked! -->
        <span title="show-hide history" data-ng-click="showHistory = !showHistory;"><span data-ng-hide="!showHistory">Hide</span><span data-ng-hide="showHistory">Show</span> History</span>
        <div data-ng-show="showHistory">
            <table class="table table-bordered">
                <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Command</th>
                    <th>UUID</th>
                    <th>PID</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody class="table-striped table-hover">
                <tr data-ng-repeat="(key, log_data) in historyRecords"> <!-- foreach loop -->
                    <td>@{{log_data.id}}</td>
                    <td>@{{log_data.user_name}}</td>
                    <td>@{{log_data.command}} @{{log_data.parameters}}</td>
                    <td><span data-ng-click="readLog(log_data.uuid)" class="label label-info" style="cursor: pointer;">@{{log_data.uuid}}</span></td><!-- use same call as showing log. -->
                    <td>@{{log_data.pid}}</td>
                    <td>@{{log_data.executed_at*1000 | date: 'yyyy-MM-dd HH:mm:ss'}}</td>
                    <td>
                        <span data-ng-click="killProcess(log_data.uuid)" class="label label-danger" style="cursor: pointer;">Kill Process</span><!-- set history data (the pid) -->
                    </td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <td>ID</td>
                    <td>User</td>
                    <td>Command</td>
                    <td>UUID</td>
                    <td>PID</td>
                    <td>Date</td>
                    <td>Actions</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <h2>Logger</h2>
    <pre style="width: 90%; background-color: black; color: darkcyan;font-family: 'Space Mono', sans-serif;">@{{ log.content }}</pre>
</div>
