# DSM7-FshareVN

1. Vào https://www.fshare.vn/api-doc, nhập email và AppName lấy Fshare API Key cho riêng mình.
2. Nhận email từ Fshare và edit file fsharevn.php thay đổi:

   $UserAgent = '<AppName đã nhập ở trên';
   $AppKey    = '<FShare API Key từ Email>';

3. Windows: cmd. Linux: terminal và chuyển tới thư mục 2 file INFO và fsharevn.php
   Lệnh: tar zcf fshare.host *
   
