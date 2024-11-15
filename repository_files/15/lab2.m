clc;
clear;
p=4
q=3
lambda = 3;
mu = 0;
n = 4;

A = randi([1,10], n, n, n, n); 
B = randi([1, 10], n, n, n);   

ORDER = [4,1,2,3];
A_T = ipermute(A, ORDER);

disp('A');
disp(A);

disp('B');
disp(B);

disp('A_T');
disp(A_T);

k=p-mu-lambda;
v=q-mu-lambda;


for l1 = 1:n
    for s1 = 1:n
        for s2 = 1:n
            for s3 = 1:n
                D(l1, s1, s2, s3) = A(l1, s1, s2, s3)*B(s1, s2, s3);
            end
        end
    end
end

disp('D');
disp(D);

E = zeros(n,n,n);

for s1 = 1:n
    for s2 = 1:n
        for s3 = 1:n
            E(s1, s2, s3) = 1;
        end
    end
end

disp('E');
disp(E);

F = zeros(4, 4, 4);

for s1 = 1:n
    for s2 = 1:n
        for s3 = 1:n
           F(s1, s2, s3) = E(s1, s2, s3) * B(s1, s2, s3);
        end
    end
end

disp(' F');
disp(F);