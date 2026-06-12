<?php
$navigator_json = '
[

    {
       "caption":"Master Data",
       "id":"master-data",
       "icon":"fas fa-database",
       "type":"folder",
       "permitted" : 0,
       "nodes":[
         {
            "caption":"Employees",
            "icon":"fas fa-cubes",
            "type":"node",
            "permitted" : 0,
            "path":"/ie/employees"
         },
         {
            "caption":"Production Lines",
            "icon":"fas fa-cubes",
            "type":"node",
            "permitted" : 0,
            "path":"/ie/production-lines"
         },
         {
            "caption":"IE Departments",
            "icon":"fas fa-cubes",
            "type":"node",
            "permitted" : 0,
            "path":"/ie/departments"
         }




       ]
    },
    {
      "caption": "User Management",
      "id": "user-management",
      "icon": "fas fa-user-tie",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption": "Create User",
          "icon": "fas fa-user-plus",
          "type": "node",
          "permitted" : 0,
          "path": "/createUser"
        },
        {
          "caption": "User Roles",
          "icon": "fas fa-user-tag",
          "type": "node",
          "permitted" : 0,
          "path": "/userRoles"
        },
        {
          "caption": "Permissions",
          "icon": "fas fa-user-lock",
          "type": "node",
          "permitted" : 0,
          "path": "/permissions"
        }
      ]
    }



 ]
  ';
