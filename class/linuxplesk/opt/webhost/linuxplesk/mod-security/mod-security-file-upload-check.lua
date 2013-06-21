#!/usr/bin/lua

-- Lua script to check HTTP file upload type

function main(filename)
        -- The system command we want to call
        local cmd = "/usr/bin/file --brief"

        local f = io.popen(cmd .. " " .. filename)
        local l = trim(f:read("*a"))

        if l == "PHP script text" or string.match(l,"script text executable$") or string.match(l,"^ELF .*-bit LSB executable") then
                m.log(0, "BLOCKED UPLOAD: " .. l .. " " .. filename)
                return "Upload blocked"
        end

        return nill
end

function trim (s)
        return (string.gsub(s, "^%s*(.-)%s*$", "%1"))
end
