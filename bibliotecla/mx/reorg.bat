@echo off
@mx %1 now -all create=reorg
@copy reorg.* %2 /y
@del reorg.*
@exit